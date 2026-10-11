<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\Messaging;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Storage\FileCleanupRequested;
use Shared\Application\Contract\Transaction\TransactionAlreadyActiveException;
use Shared\Application\Port\Outbound\{CurrentActorPort, FileStoragePort, UuidGeneratorPort};
use Shared\Infrastructure\EventSubscriber\FileCleanupSubscriber;
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, DurableEventContext, OutboxEvent, RootTransactionManagerAdapter, TransactionalEventDispatcher, TransactionalFileCleanupAdapter};
use Shared\Infrastructure\Persistence\Doctrine\Record\ConsumedEventRecord;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function dirname;
use function hash;
use function iterator_to_array;

/**
 * Class TransactionalFileCleanupTest
 *
 * Exercises real PostgreSQL commits and consumer receipts on independent connections and a native Doctrine transport.
 *
 * @category Integration Test
 */
final class TransactionalFileCleanupTest extends TestCase
{
  private const string EVENT = 'bc000000-0000-4000-8000-000000000093';

  private const string RUN = 'bc000000-0000-4000-8000-000000000094';

  private const string QUEUE = 'fireguard_file_cleanup_regression';

  private Connection $writer;

  private Connection $worker;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  protected function setUp(): void
  {
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->writer = DriverManager::getConnection(['url' => $url]);
    $this->worker = DriverManager::getConnection(['url' => $url]);
    $this->sender = $this->transport($this->writer);
    $this->receiver = $this->transport($this->worker);
    $this->sender->setup();
    $this->clearRows();
  }

  protected function tearDown(): void
  {
    foreach ([$this->writer, $this->worker] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clearRows();
    $this->writer->close();
    $this->worker->close();
    parent::tearDown();
  }

  public function testRollbackPreservesBytesAndCommitMakesCleanupVisibleToAnotherConnection(): void
  {
    $cleanup = $this->cleanup();
    $root = new RootTransactionManagerAdapter($this->writer);
    $caught = null;
    $operation = function () use ($cleanup): void {
      $this->insertRun();
      $cleanup->request('obsolete.pdf');
      self::assertSame(0, $this->receiver->getMessageCount());

      throw new RuntimeException('late revision failure');
    };

    try {
      $root->transactional($operation);
    } catch (RuntimeException $failure) {
      $caught = $failure;
    }
    self::assertInstanceOf(RuntimeException::class, $caught);
    self::assertSame('late revision failure', $caught->getMessage());
    self::assertSame(0, $this->receiver->getMessageCount());
    self::assertSame(0, $this->worker->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE id = ?', [self::RUN]));

    $root->transactional(function () use ($cleanup): void {
      $this->insertRun();
      $cleanup->request('obsolete.pdf');
      self::assertSame(0, $this->receiver->getMessageCount());
    });
    self::assertSame(1, $this->receiver->getMessageCount());
    self::assertSame(1, $this->worker->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE id = ?', [self::RUN]));
    $envelopes = iterator_to_array($this->receiver->get());
    self::assertCount(1, $envelopes);
    $message = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(FileCleanupRequested::class, $message->event);
    self::assertSame('obsolete.pdf', $message->event->storagePath);
    self::assertSame(self::EVENT, $message->id);
    $this->receiver->ack($envelopes[0]);
  }

  public function testAnOuterTransactionIsRejectedBeforeAnyOwningWrite(): void
  {
    $this->writer->beginTransaction();

    try {
      new RootTransactionManagerAdapter($this->writer)->transactional(function (): void {
        $this->insertRun();
      });
      self::fail('The root adapter must reject an enclosing transaction.');
    } catch (TransactionAlreadyActiveException) {
      self::assertSame(0, $this->writer->fetchOne('SELECT COUNT(*) FROM automation_runs WHERE id = ?', [self::RUN]));
    }
    $this->writer->rollBack();
  }

  public function testFailedDeleteRollsBackReceiptAndRetryDeletesOnlyOnceAfterConfirmation(): void
  {
    $context = new DurableEventContext();
    $storage = $this->createMock(FileStoragePort::class);
    $calls = 0;
    $storage->expects(self::exactly(2))->method('delete')->with('obsolete.pdf')->willReturnCallback(static function () use (&$calls): void {
      if (1 === ++$calls) {
        throw new RuntimeException('storage unavailable');
      }
    });
    $subscriber = new FileCleanupSubscriber($context, new DoctrineIdempotentConsumerAdapter($this->worker), $storage);
    $event = new FileCleanupRequested('obsolete.pdf');

    try {
      $context->deliver(self::EVENT, static fn () => $subscriber->onCleanup($event));
      self::fail('The failed deletion must be retried by Messenger.');
    } catch (RuntimeException $failure) {
      self::assertSame('storage unavailable', $failure->getMessage());
    }
    $receipt = hash('sha256', self::EVENT . "\0" . FileCleanupSubscriber::class);
    self::assertSame(0, $this->writer->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$receipt]));
    $context->deliver(self::EVENT, static fn () => $subscriber->onCleanup($event));
    self::assertSame(1, $this->writer->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$receipt]));
    $context->deliver(self::EVENT, static fn () => $subscriber->onCleanup($event));
    self::assertSame(2, $calls);
  }

  public function testRolledBackManagedStateIsNotReusedByTheNextCommand(): void
  {
    $configuration = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 5) . '/src/Shared/Infrastructure/Persistence/Doctrine/Record'], true);
    $configuration->setNamingStrategy(new UnderscoreNamingStrategy());
    $configuration->enableNativeLazyObjects(true);
    $entityManager = new EntityManager($this->writer, $configuration);
    $root = new RootTransactionManagerAdapter($this->writer, $entityManager);
    $record = new ConsumedEventRecord();
    $record->id = hash('sha256', self::EVENT . ':state');
    $record->consumedAt = new DateTimeImmutable();
    $caught = null;
    $operation = static function () use ($entityManager, $record): void {
      $entityManager->persist($record);
      $entityManager->flush();
      self::assertTrue($entityManager->contains($record));

      throw new RuntimeException('late persistence failure');
    };

    try {
      $root->transactional($operation);
    } catch (RuntimeException $failure) {
      $caught = $failure;
    }
    self::assertInstanceOf(RuntimeException::class, $caught);
    self::assertSame('late persistence failure', $caught->getMessage());
    self::assertFalse($entityManager->contains($record));
    $root->transactional(static function () use ($entityManager, $record): void {
      self::assertNull($entityManager->find(ConsumedEventRecord::class, $record->id));
      $entityManager->persist($record);
      $entityManager->flush();
    });
    self::assertSame(1, $this->worker->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$record->id]));
    $entityManager->clear();
  }

  private function cleanup(): TransactionalFileCleanupAdapter
  {
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn(self::EVENT);

    return new TransactionalFileCleanupAdapter(new TransactionalEventDispatcher($this->writer, $this->sender, $ids, $this->createStub(CurrentActorPort::class)));
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport(
      'doctrine://main?queue_name=' . self::QUEUE . '&auto_setup=false',
      ['use_notify' => false],
      new PhpSerializer(),
    );
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function insertRun(): void
  {
    $this->writer->executeStatement(
      'INSERT INTO automation_runs (id, rule_key, organization_id, subject_id, status, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
      [self::RUN, 'file_cleanup_regression', self::RUN, self::RUN, 'succeeded'],
    );
  }

  private function clearRows(): void
  {
    $this->writer->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [self::QUEUE]);
    $this->writer->executeStatement('DELETE FROM automation_runs WHERE id = ?', [self::RUN]);
    $this->writer->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', self::EVENT . "\0" . FileCleanupSubscriber::class)]);
    $this->writer->executeStatement('DELETE FROM consumed_events WHERE id = ?', [hash('sha256', self::EVENT . ':state')]);
  }
}
