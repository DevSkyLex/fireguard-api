<?php

declare(strict_types=1);

namespace Tests\Integration\User\Application;

use ApiPlatform\Metadata\Get;
use Authorization\Infrastructure\Adapter\Lifecycle\AuthorizationDataLifecycleAdapter;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ConnectionRegistry;
use OAuth\Infrastructure\Adapter\Lifecycle\OAuthDataLifecycleAdapter;
use Otp\Application\Port\Outbound\Totp\TotpSecretCipherPort;
use Otp\Infrastructure\Adapter\Lifecycle\OtpDataLifecycleAdapter;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Repository\TotpEnrollmentRepository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Session\Infrastructure\Adapter\Lifecycle\SessionDataLifecycleAdapter;
use Shared\Application\Contract\Storage\{FileCleanupRequested, FileStorageException};
use Shared\Application\Contract\Transaction\{CommitOutcomeUnknownException, TransactionAlreadyActiveException};
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{CurrentActorPort, DurableEventContextPort, FileCleanupPort, FileStoragePort, IdempotentConsumerPort, LoggerPort, UuidGeneratorPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Infrastructure\EventSubscriber\FileCleanupSubscriber;
use Shared\Infrastructure\Image\ImageInputValidationAdapter;
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, OutboxEvent, RootTransactionManagerAdapter, TransactionalEventDispatcher, TransactionalFileCleanupAdapter};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Uid\Uuid;
use Tests\Support\Factory\UserTestFactory;
use Throwable;
use TrustedDevice\Infrastructure\Adapter\Lifecycle\TrustedDeviceDataLifecycleAdapter;
use User\Application\Contract\User\{AvatarPaths, AvatarVariants};
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\UseCase\Command\Avatar\UploadUserAvatar\{UploadUserAvatarCommand, UploadUserAvatarHandler, UploadUserAvatarResult};
use User\Application\UseCase\Command\User\DeleteUser\{DeleteUserCommand, DeleteUserHandler};
use User\Application\UseCase\Query\Avatar\GetUserAvatar\GetUserAvatarHandler;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\UserProfile;
use User\Infrastructure\Adapter\User\{AvatarPersistenceAdapter, UserDataPurgeAdapter};
use User\Infrastructure\Image\AvatarResizer;
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Repository\UserRepository;
use User\Presentation\Api\Provider\User\GetUserAvatarProvider;

use function array_key_exists;
use function base64_decode;
use function hash;
use function iterator_to_array;

/** Native auth commits and real Doctrine envelopes observed from independent connections.
 * @category Integration Test
 */
final class UserAvatarAtomicityTest extends KernelTestCase
{
  private const string USER = 'b7d24001-3a71-4c7a-9f00-000000000001';

  private const string A = 'b7d24001-3a71-4c7a-9f00-000000000002';

  private const string B = 'b7d24001-3a71-4c7a-9f00-000000000003';

  private const string QUEUE = 'user_avatar_atomicity';

  private Connection $writer;

  private Connection $observer;

  private EntityManagerInterface $manager;

  private EntityManagerInterface $reader;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  private FileStoragePort $storage;

  /**
   * @var array<string,string>
   */
  private array $blobs = [];

  /**
   * @var list<string>
   */
  private array $eventIds = [];

  private int $deletions = 0;

  private bool $failDelete = false;

  /**
   * @var (callable():void)|null
   */
  private mixed $onWrite = null;

  protected function setUp(): void
  {
    self::bootKernel();
    $url = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->writer = DriverManager::getConnection(['url' => $url]);
    $this->observer = DriverManager::getConnection(['url' => $url]);
    $this->manager = $this->manager($this->writer);
    $this->reader = $this->manager($this->observer);
    $this->sender = $this->transport($this->writer);
    $this->receiver = $this->transport($this->observer);
    $this->sender->setup();
    $this->clearRows();
    $user = UserTestFactory::createActive(id: self::USER, email: 'avatar-atomicity@example.test', username: 'avatar_atomicity');
    $user->updateProfile(new UserProfile('Original', 'Profile', 'https://api.example.test/api/users/' . self::USER . '/avatar/256.webp?v=1'));
    new UserRepository($this->manager, new UserMapper())->save($user);
    foreach (AvatarVariants::SIZES as $size) {
      $this->blobs[AvatarPaths::variant(self::USER, null, $size)] = 'legacy-' . $size;
    }
    $this->storage = $this->createStub(FileStoragePort::class);
    $this->storage->method('write')->willReturnCallback(function (string $path, string $bytes): void {
      $this->blobs[$path] = $bytes;
      if (null !== $this->onWrite) {
        $callback = $this->onWrite;
        $this->onWrite = null;
        $callback();
      }
    });
    $this->storage->method('exists')->willReturnCallback(fn (string $path): bool => array_key_exists($path, $this->blobs));
    $this->storage->method('read')->willReturnCallback(fn (string $path): string => $this->blobs[$path] ?? throw FileStorageException::readFailed($path));
    $this->storage->method('delete')->willReturnCallback(function (string $path): void {
      if ($this->failDelete) {
        $this->failDelete = false;

        throw new RuntimeException('Storage unavailable');
      }
      unset($this->blobs[$path]);
      ++$this->deletions;
    });
  }

  protected function tearDown(): void
  {
    foreach ([$this->writer, $this->observer] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->manager->clear();
    $this->reader->clear();
    $this->clearRows();
    $this->writer->close();
    $this->observer->close();
    parent::tearDown();
  }

  #[Test]
  public function lateSqlFailureRollsBackProfilePointerAndTheAuthOutbox(): void
  {
    try {
      $this->upload(self::A, cleanup: $this->cleanup($this->writer, true))($this->command());
      self::fail('The late SQL failure must propagate.');
    } catch (Throwable $failure) {
      self::assertStringContainsString('username', $failure->getMessage());
    }
    self::assertNull($this->observer->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
    $url = $this->observer->fetchOne('SELECT avatar_url FROM users WHERE id = ?', [self::USER]);
    self::assertIsString($url);
    self::assertStringEndsWith('?v=1', $url);
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertCount(4, $this->blobs);
    foreach (AvatarPaths::variants(self::USER, self::A) as $path) {
      self::assertArrayNotHasKey($path, $this->blobs);
    }
  }

  #[Test]
  public function resultCaptureFailureRollsBackProfilePointerOutboxAndAttemptBytes(): void
  {
    $failure = new RuntimeException('snapshot encoding failed');
    $execution = $this->createMock(HttpIdempotencyExecutionPort::class);
    $execution->expects(self::once())->method('captureResult')->willReturnCallback(function (ResultMessage $result) use ($failure): never {
      self::assertTrue($this->writer->isTransactionActive());
      self::assertInstanceOf(UploadUserAvatarResult::class, $result);
      self::assertSame(self::USER, $result->userId);
      self::assertSame(self::A, $this->writer->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
      self::assertNull($this->observer->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
      self::assertSame(4, $this->sender->getMessageCount());
      self::assertSame(0, $this->receiver->getMessageCount());

      throw $failure;
    });

    try {
      $this->upload(self::A, idempotency: $execution)($this->command());
      self::fail('A failed result snapshot must reject the final avatar commit.');
    } catch (Throwable $actual) {
      self::assertSame($failure, $actual);
    }
    self::assertNull($this->observer->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertCount(4, $this->blobs);
    foreach (AvatarPaths::variants(self::USER, self::A) as $path) {
      self::assertArrayNotHasKey($path, $this->blobs);
    }
  }

  #[Test]
  public function aPreparedLosingAttemptCannotDeleteTheConcurrentWinner(): void
  {
    $this->onWrite = function (): void {
      $this->upload(self::B, $this->reader)($this->command());
    };

    try {
      $this->upload(self::A, cleanup: $this->cleanup($this->writer, true))($this->command());
      self::fail('The late SQL failure must propagate.');
    } catch (Throwable) {
      self::assertSame(self::B, $this->observer->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
    }
    foreach (AvatarPaths::variants(self::USER, self::B) as $path) {
      self::assertArrayHasKey($path, $this->blobs);
    }
    foreach (AvatarPaths::variants(self::USER, self::A) as $path) {
      self::assertArrayNotHasKey($path, $this->blobs);
    }
    self::assertSame(4, $this->receiver->getMessageCount());
  }

  #[Test]
  public function replacementCleanupRetriesWithAuthReceiptsAndAcknowledgedKeysDoNotReplay(): void
  {
    $this->upload(self::A)($this->command());
    $this->deliverCleanup();
    self::assertCount(4, $this->blobs);
    $this->upload(self::B)($this->command());
    self::assertCount(8, $this->blobs);
    $envelopes = $this->envelopes();
    self::assertCount(4, $envelopes);
    foreach ($envelopes as $index => $envelope) {
      $message = $envelope->getMessage();
      self::assertInstanceOf(OutboxEvent::class, $message);
      self::assertInstanceOf(FileCleanupRequested::class, $message->event);
      self::assertSame('auth', $message->event->owningDatabase);
      $consumer = $this->consumer($message->id);
      if (0 === $index) {
        $this->failDelete = true;

        try {
          $consumer->onCleanup($message->event);
          self::fail('The first storage failure must remain retryable.');
        } catch (RuntimeException) {
          self::assertArrayHasKey($message->event->storagePath, $this->blobs);
        }
      }
      $before = $this->deletions;
      $consumer->onCleanup($message->event);
      $consumer->onCleanup($message->event);
      self::assertSame($before + 1, $this->deletions);
      $this->receiver->ack($envelope);
    }
    self::assertCount(4, $this->blobs);
    foreach (AvatarPaths::variants(self::USER, self::B) as $path) {
      self::assertArrayHasKey($path, $this->blobs);
    }
  }

  #[Test]
  public function accountDeletionRollbackPreservesTheAvatarThenCommittedDeletionCleansDurably(): void
  {
    $this->upload(self::A)($this->command());
    $this->deliverCleanup();
    $users = new UserRepository($this->manager, new UserMapper());
    $failing = $this->createStub(UserRepositoryPort::class);
    $failing->method('findById')->willReturnCallback($users->findById(...));
    $failing->method('delete')->willReturnCallback(function (User $user) use ($users): void {
      $users->delete($user);
      $this->writer->executeStatement('UPDATE users SET username = NULL WHERE id = ?', [self::USER]);

      throw new RuntimeException('Late deletion failure');
    });

    try {
      new DeleteUserHandler($failing, $this->purge())(new DeleteUserCommand(self::USER));
      self::fail('Deletion must roll back.');
    } catch (RuntimeException) {
      self::assertSame(self::A, $this->observer->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
    }
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertCount(4, $this->blobs);
    new DeleteUserHandler($users, $this->purge())(new DeleteUserCommand(self::USER));
    self::assertFalse($this->observer->fetchOne('SELECT id FROM users WHERE id = ?', [self::USER]));
    self::assertCount(4, $this->blobs);
    self::assertSame(4, $this->receiver->getMessageCount());
    $this->deliverCleanup();
    self::assertSame([], $this->blobs);
  }

  #[Test]
  public function aRealCommitWithALostAcknowledgementPreservesTheWinningFiles(): void
  {
    $this->writer->getConfiguration()->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
    $uncertain = new class ($this->writer->getParams(), $this->writer->getDriver(), $this->writer->getConfiguration()) extends Connection {
      public function commit(): bool
      {
        $outermost = 1 === $this->getTransactionNestingLevel();
        $success = parent::commit();
        if ($outermost) {
          throw new RuntimeException('Commit acknowledgement lost after PostgreSQL committed');
        }

        return $success;
      }
    };
    $manager = $this->manager($uncertain);

    try {
      $this->upload(self::A, $manager)($this->command());
      self::fail('The commit outcome must be reported as unknown.');
    } catch (CommitOutcomeUnknownException) {
      self::assertSame(self::A, $this->observer->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
      self::assertSame(4, $this->receiver->getMessageCount());
      self::assertSame(0, $this->deletions);
      foreach (AvatarPaths::variants(self::USER, self::A) as $path) {
        self::assertArrayHasKey($path, $this->blobs);
      }
    } finally {
      $manager->clear();
      $uncertain->close();
    }
  }

  #[Test]
  public function anActualOuterTransactionRefusesTheUploadBeforeStorageWrites(): void
  {
    $original = $this->blobs;
    $this->writer->beginTransaction();

    try {
      $this->upload(self::A)($this->command());
      self::fail('An enclosing transaction must be refused.');
    } catch (TransactionAlreadyActiveException) {
      self::assertSame($original, $this->blobs);
      self::assertSame(0, $this->deletions);
      self::assertSame(0, $this->receiver->getMessageCount());
    } finally {
      $this->writer->rollBack();
    }
  }

  #[Test]
  public function canonicalPublicUrlsReadLegacyAndCurrentVariantsWithTheSameCacheHeaders(): void
  {
    $legacy = $this->publicAvatar();
    self::assertSame('legacy-64', $legacy->getContent());
    self::assertSame('image/webp', $legacy->headers->get('Content-Type'));
    self::assertSame('max-age=86400, public', $legacy->headers->get('Cache-Control'));
    $this->upload(self::A)($this->command());
    $current = $this->publicAvatar();
    self::assertSame($this->blobs[AvatarPaths::variant(self::USER, self::A, 64)], $current->getContent());
    self::assertSame('nosniff', $current->headers->get('X-Content-Type-Options'));
    self::assertSame($legacy->headers->get('Cache-Control'), $current->headers->get('Cache-Control'));
  }

  private function publicAvatar(): \Symfony\Component\HttpFoundation\Response
  {
    $handler = new GetUserAvatarHandler(new UserRepository($this->reader, new UserMapper()), $this->storage, $this->avatars($this->reader));
    $bus = $this->createStub(QueryBusPort::class);
    $bus->method('ask')->willReturnCallback($handler(...));

    return new GetUserAvatarProvider($bus)->provide(new Get(), ['id' => self::USER, 'size' => 80]);
  }

  private function upload(string $generation, ?EntityManagerInterface $manager = null, ?FileCleanupPort $cleanup = null, ?HttpIdempotencyExecutionPort $idempotency = null): UploadUserAvatarHandler
  {
    $manager ??= $this->manager;
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn($generation);

    return new UploadUserAvatarHandler(new UserRepository($manager, new UserMapper()), new AvatarResizer($this->storage, new ImageInputValidationAdapter()), $this->avatars($manager), new RootTransactionManagerAdapter($manager->getConnection(), $manager), $cleanup ?? $this->cleanup($manager->getConnection()), $ids, $this->createStub(LoggerPort::class), $idempotency);
  }

  private function command(): UploadUserAvatarCommand
  {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    self::assertIsString($png);

    return new UploadUserAvatarCommand(self::USER, $png, 'https://api.example.test', 2);
  }

  private function avatars(EntityManagerInterface $manager): AvatarPersistenceAdapter
  {
    return new AvatarPersistenceAdapter($manager, new TotpEnrollmentRepository($manager, new TotpEnrollmentMapper($this->createStub(TotpSecretCipherPort::class))));
  }

  private function purge(): UserDataPurgeAdapter
  {
    $manager = $this->manager;

    return new UserDataPurgeAdapter($manager, new TotpEnrollmentRepository($manager, new TotpEnrollmentMapper($this->createStub(TotpSecretCipherPort::class))), new AuthorizationDataLifecycleAdapter($manager), new SessionDataLifecycleAdapter($manager), new OtpDataLifecycleAdapter($manager), new TrustedDeviceDataLifecycleAdapter($manager), new OAuthDataLifecycleAdapter($manager), $this->avatars($manager), $this->cleanup($this->writer));
  }

  private function cleanup(Connection $connection, bool $fail = false): FileCleanupPort
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturnCallback(function (): string {
      $id = Uuid::v7()->toRfc4122();
      $this->eventIds[] = $id;

      return $id;
    });
    $cleanup = new TransactionalFileCleanupAdapter(new TransactionalEventDispatcher($connection, $this->transport($connection), $ids, $this->createStub(CurrentActorPort::class)), 'auth');
    if (!$fail) {
      return $cleanup;
    }
    $failing = $this->createStub(FileCleanupPort::class);
    $failing->method('request')->willReturnCallback(function (string $path) use ($cleanup, $connection): void {
      $cleanup->request($path);
      $connection->executeStatement('UPDATE users SET username = NULL WHERE id = ?', [self::USER]);
    });

    return $failing;
  }

  private function consumer(string $eventId): FileCleanupSubscriber
  {
    $context = $this->createStub(DurableEventContextPort::class);
    $context->method('eventId')->willReturn($eventId);
    $mainReceipts = $this->createMock(IdempotentConsumerPort::class);
    $mainReceipts->expects(self::never())->method('consume');

    return new FileCleanupSubscriber($context, $mainReceipts, $this->storage, new DoctrineIdempotentConsumerAdapter($this->observer));
  }

  /**
   * @return list<\Symfony\Component\Messenger\Envelope>
   */
  private function envelopes(): array
  {
    $envelopes = [];
    while (true) {
      $ready = iterator_to_array($this->receiver->get());
      if ([] === $ready) {
        return $envelopes;
      }
      foreach ($ready as $envelope) {
        $envelopes[] = $envelope;
      }
    }
  }

  private function deliverCleanup(): void
  {
    foreach ($this->envelopes() as $envelope) {
      $message = $envelope->getMessage();
      self::assertInstanceOf(OutboxEvent::class, $message);
      self::assertInstanceOf(FileCleanupRequested::class, $message->event);
      $this->consumer($message->id)->onCleanup($message->event);
      $this->receiver->ack($envelope);
    }
  }

  private function manager(Connection $connection): EntityManagerInterface
  {
    $template = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $template);

    return new EntityManager($connection, $template->getConfiguration(), new EventManager());
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://auth?queue_name=' . self::QUEUE . '&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function clearRows(): void
  {
    $this->writer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->writer->executeStatement('DELETE FROM users WHERE id = ?', [self::USER]);
    foreach ($this->eventIds as $id) {
      $this->writer->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', $id . "\0" . FileCleanupSubscriber::class)]);
    }
  }
}
