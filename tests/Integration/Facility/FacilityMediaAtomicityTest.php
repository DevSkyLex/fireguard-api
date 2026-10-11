<?php

declare(strict_types=1);

namespace Tests\Integration\Facility;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\Persistence\ConnectionRegistry;
use Facility\Application\Port\Outbound\FacilityPlanReferenceCleanupPort;
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentHandler};
use Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\{DeleteFacilityAttachmentCommand, DeleteFacilityAttachmentHandler};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\{FacilityAttachmentRepository, FacilityRepository};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Contract\Transaction\TransactionAlreadyActiveException;
use Shared\Application\Port\Outbound\{CurrentActorPort, FileStoragePort, RootTransactionManagerPort, UuidGeneratorPort};
use Shared\Infrastructure\EventSubscriber\FileCleanupSubscriber;
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, DurableEventContext, OutboxEvent, RootTransactionManagerAdapter, TransactionalEventDispatcher, TransactionalFileCleanupAdapter};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function hash;
use function iterator_to_array;

/** PostgreSQL final commits and native Doctrine outbox on independent producer/worker connections. */
final class FacilityMediaAtomicityTest extends KernelTestCase
{
  private const string ORGANIZATION = 'dc120000-0000-4000-8000-000000000001';

  private const string FACILITY = 'dc120000-0000-4000-8000-000000000002';

  private const string ATTACHMENT = 'dc120000-0000-4000-8000-000000000003';

  private const string ATTEMPT = 'dc120000-0000-4000-8000-000000000004';

  private const string EVENT = 'dc120000-0000-4000-8000-000000000005';

  private const string QUEUE = 'facility_media_atomicity';

  private Connection $producer;

  private Connection $worker;

  private EntityManagerInterface $entityManager;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  protected function setUp(): void
  {
    self::bootKernel();
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->producer = DriverManager::getConnection(['url' => $url]);
    $this->worker = DriverManager::getConnection(['url' => $url]);
    /** @var EntityManagerInterface $configured */
    $configured = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = new EntityManager($this->producer, $configured->getConfiguration(), $configured->getEventManager());
    $this->sender = $this->transport($this->producer);
    $this->receiver = $this->transport($this->worker);
    $this->sender->setup();
    $this->clearRows();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'Facility media transaction test';
    $organization->slug = 'facility-media-transaction-test';
    $organization->ownerUserId = self::ORGANIZATION;
    $organization->createdByUserId = self::ORGANIZATION;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable();
    $organization->updatedAt = $organization->createdAt;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY;
    $facility->organizationId = self::ORGANIZATION;
    $facility->type = 'site';
    $facility->name = 'Site';
    $facility->status = 'active';
    $facility->createdAt = new DateTimeImmutable();
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($organization);
    $this->entityManager->flush();
    $this->entityManager->persist($facility);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    foreach ([$this->producer, $this->worker] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->entityManager->clear();
    $this->clearRows();
    $this->entityManager->close();
    $this->producer->close();
    $this->worker->close();
    parent::tearDown();
  }

  #[Test]
  public function aLateFailureRollsBackMetadataAndCompensatesOnlyItsAttempt(): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('write')->with($this->path(), '%PDF');
    $storage->expects(self::once())->method('delete')->with($this->path());

    try {
      $this->upload($storage, $this->failingTransactions())($this->command());
      self::fail('The late transaction failure must propagate.');
    } catch (RuntimeException $exception) {
      self::assertSame('late operation failure', $exception->getMessage());
    }
    self::assertSame(0, $this->attachmentCount());
    self::assertSame(0, $this->receiver->getMessageCount());
  }

  #[Test]
  public function replayPreservesCommittedWinnerMetadataAndBytes(): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('write')->with($this->path(), '%PDF');
    $storage->expects(self::never())->method('delete');
    $handler = $this->upload($storage, new RootTransactionManagerAdapter($this->producer));
    $first = $handler($this->command());
    $second = $handler(new AddFacilityAttachmentCommand(self::ORGANIZATION, self::FACILITY, 'loser.pdf', 'different bytes', 'application/pdf', 15, attachmentId: self::ATTACHMENT));
    self::assertSame($first->fileName, $second->fileName);
    self::assertSame('new.pdf', $this->worker->fetchOne('SELECT file_name FROM facility_attachments WHERE id = ?', [self::ATTACHMENT]));
    self::assertSame($this->path(), $this->worker->fetchOne('SELECT storage_path FROM facility_attachments WHERE id = ?', [self::ATTACHMENT]));
  }

  #[Test]
  public function anOuterTransactionIsRejectedBeforeTheFileWrite(): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');
    $this->producer->beginTransaction();

    try {
      $this->upload($storage, new RootTransactionManagerAdapter($this->producer))($this->command());
      self::fail('An outer transaction must be rejected.');
    } catch (TransactionAlreadyActiveException) {
      self::assertSame(1, $this->producer->getTransactionNestingLevel());
    } finally {
      $this->producer->rollBack();
    }
  }

  #[Test]
  public function deletingAndEnqueuingCleanupRollBackTogetherOnALateFailure(): void
  {
    $this->upload($this->createStub(FileStoragePort::class), new RootTransactionManagerAdapter($this->producer))($this->command());

    try {
      $this->delete($this->failingTransactions())(new DeleteFacilityAttachmentCommand(self::ORGANIZATION, self::FACILITY, self::ATTACHMENT, expectedRevision: 1));
      self::fail('The late transaction failure must propagate.');
    } catch (RuntimeException $exception) {
      self::assertSame('late operation failure', $exception->getMessage());
    }
    self::assertSame(1, $this->attachmentCount());
    self::assertSame(0, $this->receiver->getMessageCount());
  }

  #[Test]
  public function cleanupRunsAfterCommitAndItsReceiptPreventsRepeatedDeletion(): void
  {
    $this->upload($this->createStub(FileStoragePort::class), new RootTransactionManagerAdapter($this->producer))($this->command());
    $this->delete(new RootTransactionManagerAdapter($this->producer))(new DeleteFacilityAttachmentCommand(self::ORGANIZATION, self::FACILITY, self::ATTACHMENT, expectedRevision: 1));
    self::assertSame(0, $this->attachmentCount());
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $message = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(FileCleanupRequested::class, $message->event);
    self::assertSame($this->path(), $message->event->storagePath);
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::once())->method('delete')->with($this->path());
    $context = new DurableEventContext();
    $consumer = new FileCleanupSubscriber($context, new DoctrineIdempotentConsumerAdapter($this->worker), $storage);
    $context->deliver($message->id, static fn () => $consumer->onCleanup($message->event));
    $context->deliver($message->id, static fn () => $consumer->onCleanup($message->event));
    $this->receiver->ack($envelopes[0]);
  }

  private function upload(FileStoragePort $storage, RootTransactionManagerPort $transactions): AddFacilityAttachmentHandler
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::ATTEMPT);

    return new AddFacilityAttachmentHandler(new FacilityRepository($this->entityManager), new FacilityAttachmentRepository($this->entityManager), $storage, $ids, $transactions, $this->cleanup(), $this->createStub(OrganizationAuthorizationPort::class));
  }

  private function delete(RootTransactionManagerPort $transactions): DeleteFacilityAttachmentHandler
  {
    return new DeleteFacilityAttachmentHandler(new FacilityRepository($this->entityManager), new FacilityAttachmentRepository($this->entityManager), $this->cleanup(), $this->createStub(FacilityPlanReferenceCleanupPort::class), $transactions, $this->createStub(OrganizationAuthorizationPort::class));
  }

  private function cleanup(): TransactionalFileCleanupAdapter
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::EVENT);

    return new TransactionalFileCleanupAdapter(new TransactionalEventDispatcher($this->producer, $this->sender, $ids, $this->createStub(CurrentActorPort::class)));
  }

  private function failingTransactions(): RootTransactionManagerPort
  {
    $root = new RootTransactionManagerAdapter($this->producer);

    return new readonly class ($root) implements RootTransactionManagerPort {
      public function __construct(private RootTransactionManagerPort $root)
      {
      }

      public function assertCanStart(): void
      {
        $this->root->assertCanStart();
      }

      public function transactional(callable $operation): mixed
      {
        return $this->root->transactional(static function () use ($operation): never {
          $operation();

          throw new RuntimeException('late operation failure');
        });
      }
    };
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=' . self::QUEUE . '&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function command(): AddFacilityAttachmentCommand
  {
    return new AddFacilityAttachmentCommand(self::ORGANIZATION, self::FACILITY, 'new.pdf', '%PDF', 'application/pdf', 4, attachmentId: self::ATTACHMENT);
  }

  private function path(): string
  {
    return 'facility/' . self::FACILITY . '/attachments/' . self::ATTEMPT . '_new.pdf';
  }

  private function attachmentCount(): int
  {
    /** @var int|string $count */
    $count = $this->worker->fetchOne('SELECT COUNT(*) FROM facility_attachments WHERE id = ?', [self::ATTACHMENT]);

    return (int) $count;
  }

  private function clearRows(): void
  {
    $this->producer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->producer->executeStatement('DELETE FROM organizations WHERE id = ?', [self::ORGANIZATION]);
    $this->producer->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', self::EVENT . "\0" . FileCleanupSubscriber::class)]);
  }
}
