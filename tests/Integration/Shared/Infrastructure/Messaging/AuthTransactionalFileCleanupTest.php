<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\Messaging;

use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use RuntimeException;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Contract\Transaction\TransactionAlreadyActiveException;
use Shared\Application\Port\Outbound\{CurrentActorPort, FileStoragePort, UuidGeneratorPort};
use Shared\Infrastructure\EventDispatcher\{CompositeIntegrationEventDecoder, SymfonyEventDispatcherAdapter};
use Shared\Infrastructure\EventSubscriber\FileCleanupSubscriber;
use Shared\Infrastructure\Messaging\Outbox\{DeliverOutboxEventHandler, DoctrineIdempotentConsumerAdapter, DurableEventContext, OutboxEvent, RootTransactionManagerAdapter, TransactionalEventDispatcher, TransactionalFileCleanupAdapter};
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function hash;
use function iterator_to_array;
use function serialize;
use function unserialize;

/**
 * Class AuthTransactionalFileCleanupTest
 *
 * Verifies auth mutation/enqueue atomicity, separately owned receipts and legacy main replay using native PostgreSQL connections.
 *
 * @category Integration Test
 */
final class AuthTransactionalFileCleanupTest extends TestCase
{
  private const string EVENT = 'bc000000-0000-4000-8000-000000000097';

  private const string LEGACY_EVENT = 'bc000000-0000-4000-8000-000000000098';

  private Connection $writer;

  private Connection $worker;

  private Connection $main;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  protected function setUp(): void
  {
    $authUrl = $_ENV['AUTH_DATABASE_URL'] ?? $_SERVER['AUTH_DATABASE_URL'] ?? null;
    $mainUrl = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($authUrl);
    self::assertIsString($mainUrl);
    $this->writer = DriverManager::getConnection(['url' => $authUrl]);
    $this->worker = DriverManager::getConnection(['url' => $authUrl]);
    $this->main = DriverManager::getConnection(['url' => $mainUrl]);
    $this->sender = $this->transport($this->writer);
    $this->receiver = $this->transport($this->worker);
    $this->sender->setup();
    $this->clearRows();
  }

  protected function tearDown(): void
  {
    foreach ([$this->writer, $this->worker, $this->main] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clearRows();
    foreach ([$this->writer, $this->worker, $this->main] as $connection) {
      $connection->close();
    }
    parent::tearDown();
  }

  public function testAuthMutationAndEnqueueCommitTogetherThenDeliveryUsesOnlyAuthReceipts(): void
  {
    $root = new RootTransactionManagerAdapter($this->writer);
    $cleanup = $this->cleanup();
    $caught = null;

    try {
      $root->transactional(function () use ($cleanup): void {
        $this->insertMutation();
        $cleanup->request('avatars/obsolete.webp');
        self::assertSame(0, $this->queuedEventCount());
        self::assertSame(0, $this->mutationCount());

        throw new RuntimeException('late auth persistence failure');
      });
    } catch (RuntimeException $failure) {
      $caught = $failure;
    }
    self::assertInstanceOf(RuntimeException::class, $caught);
    self::assertSame('late auth persistence failure', $caught->getMessage());
    self::assertSame(0, $this->queuedEventCount());
    self::assertSame(0, $this->mutationCount());
    $root->transactional(function () use ($cleanup): void {
      $this->insertMutation();
      $cleanup->request('avatars/obsolete.webp');
      self::assertSame(0, $this->queuedEventCount());
    });
    self::assertSame(1, $this->queuedEventCount());
    self::assertSame(1, $this->mutationCount());
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $message = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(FileCleanupRequested::class, $message->event);
    self::assertSame('auth', $message->event->owningDatabase);
    self::assertSame('auth-actor', $message->actorUserId);
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('delete')->with('avatars/obsolete.webp');
    $handler = $this->handler($storage);
    $handler($message);
    self::assertSame(1, $this->receiptCount($this->writer, self::EVENT));
    self::assertSame(0, $this->receiptCount($this->main, self::EVENT));
    $handler($message);
    $this->receiver->ack($envelopes[0]);
    self::assertSame(0, $this->queuedEventCount());
  }

  public function testFailedAuthDeletionRollsBackOnlyAuthReceiptAndCanRetry(): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $calls = 0;
    $storage->expects(self::exactly(2))->method('delete')->with('avatars/obsolete.webp')->willReturnCallback(static function () use (&$calls): void {
      if (1 === ++$calls) {
        throw new RuntimeException('storage unavailable');
      }
    });
    $handler = $this->handler($storage);
    $message = new OutboxEvent(self::EVENT, new FileCleanupRequested('avatars/obsolete.webp', 'auth'));

    try {
      $handler($message);
      self::fail('Storage failure must propagate to the auth transport retry policy.');
    } catch (RuntimeException $failure) {
      self::assertSame('storage unavailable', $failure->getMessage());
    }
    self::assertSame(0, $this->receiptCount($this->writer, self::EVENT));
    self::assertSame(0, $this->receiptCount($this->main, self::EVENT));
    $handler($message);
    $handler($message);
    self::assertSame(2, $calls);
    self::assertSame(1, $this->receiptCount($this->writer, self::EVENT));
    self::assertSame(0, $this->receiptCount($this->main, self::EVENT));
  }

  public function testSerializedHistoricalCleanupWithoutDatabaseKeepsMainReceipt(): void
  {
    $reflection = new ReflectionClass(FileCleanupRequested::class);
    $legacy = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('storagePath')->setValue($legacy, 'equipment/legacy.pdf');
    $message = unserialize(serialize(new OutboxEvent(self::LEGACY_EVENT, $legacy)), ['allowed_classes' => [OutboxEvent::class, FileCleanupRequested::class]]);
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(FileCleanupRequested::class, $message->event);
    self::assertFalse($reflection->getProperty('owningDatabase')->isInitialized($message->event));
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('delete')->with('equipment/legacy.pdf');
    $handler = $this->handler($storage);
    $handler($message);
    $handler($message);
    self::assertSame(1, $this->receiptCount($this->main, self::LEGACY_EVENT));
    self::assertSame(0, $this->receiptCount($this->writer, self::LEGACY_EVENT));
  }

  public function testAuthRootRejectsOuterTransactionBeforeMutationOrCleanupEnqueue(): void
  {
    $this->writer->beginTransaction();

    try {
      new RootTransactionManagerAdapter($this->writer)->transactional(function (): void {
        $this->insertMutation();
        $this->cleanup()->request('avatars/obsolete.webp');
      });
      self::fail('Media commands must own their final auth commit.');
    } catch (TransactionAlreadyActiveException) {
      self::assertSame(0, $this->queuedEventCount());
      self::assertSame(0, $this->mutationCount());
    }
    $this->writer->rollBack();
  }

  private function cleanup(): TransactionalFileCleanupAdapter
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::EVENT);
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('auth-actor');

    return new TransactionalFileCleanupAdapter(new TransactionalEventDispatcher($this->writer, $this->sender, $ids, $actor), 'auth');
  }

  private function handler(FileStoragePort $storage): DeliverOutboxEventHandler
  {
    $context = new DurableEventContext();
    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new FileCleanupSubscriber($context, new DoctrineIdempotentConsumerAdapter($this->main), $storage, new DoctrineIdempotentConsumerAdapter($this->worker)));

    return new DeliverOutboxEventHandler($context, new SymfonyEventDispatcherAdapter($dispatcher, new NullLogger(), new CompositeIntegrationEventDecoder([])));
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createMock(ConnectionRegistry::class);
    $registry->method('getConnection')->with('auth')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://auth?queue_name=auth_outbox&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function queuedEventCount(): int
  {
    $count = $this->worker->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ? AND body LIKE ?', ['auth_outbox', '%' . self::EVENT . '%']);
    self::assertIsInt($count);

    return $count;
  }

  private function insertMutation(): void
  {
    $this->writer->executeStatement('INSERT INTO consumed_events (id, consumed_at) VALUES (?, CURRENT_TIMESTAMP)', [hash('sha256', self::EVENT . ':mutation')]);
  }

  private function mutationCount(): int
  {
    $count = $this->worker->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [hash('sha256', self::EVENT . ':mutation')]);
    self::assertIsInt($count);

    return $count;
  }

  private function receiptCount(Connection $connection, string $event): int
  {
    $count = $connection->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [hash('sha256', $event . "\0" . FileCleanupSubscriber::class)]);
    self::assertIsInt($count);

    return $count;
  }

  private function clearRows(): void
  {
    $this->writer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ? AND body LIKE ?', ['auth_outbox', '%' . self::EVENT . '%']);
    $this->writer->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', self::EVENT . ':mutation')]);
    foreach ([self::EVENT, self::LEGACY_EVENT] as $event) {
      foreach ([$this->writer, $this->main] as $connection) {
        $connection->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', $event . "\0" . FileCleanupSubscriber::class)]);
      }
    }
  }
}
