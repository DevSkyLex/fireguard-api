<?php

declare(strict_types=1);

namespace Tests\Integration\Billing\Infrastructure\Idempotency;

use Billing\Application\Contract\Event\ExternalBillingReconciliationRequested;
use Billing\Application\UseCase\Command\StartPortal\{StartPortalCommand, StartPortalResult};
use Billing\Infrastructure\Adapter\Idempotency\ExternalBillingReconciliationAdapter;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\Persistence\ConnectionRegistry;
use LogicException;
use RuntimeException;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\IdempotencyLease;
use Shared\Application\Port\Outbound\{CurrentActorPort, EventDispatcherPort, UuidGeneratorPort};
use Shared\Infrastructure\Messaging\Outbox\{DoctrineIdempotentConsumerAdapter, OutboxEvent, TransactionalEventDispatcher};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function iterator_to_array;

/**
 * Test ExternalBillingReconciliationTest.
 *
 * Proves the reconciliation request survives the original rollback and is durably emitted once, using the native outbox.
 *
 * @category Integration Test
 */
#[SkipDatabaseRollback]
final class ExternalBillingReconciliationTest extends KernelTestCase
{
  use RootCommitDatabaseIsolationTrait;

  /**
   * @var list<Connection> independent PostgreSQL connections
   */
  private array $connections = [];

  protected function tearDown(): void
  {
    foreach ($this->connections as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
      $connection->close();
    }
    $this->connections = [];
    parent::tearDown();
  }

  public function testFailedEnqueueRollsBackItsReceiptAndRetryEnqueuesExactlyOneNativeMessage(): void
  {
    $write = $this->connection();
    $observer = $this->connection();
    $sender = $this->transport($write);
    $receiver = $this->transport($observer);
    $sender->setup();
    $operation = new HttpIdempotencyOperation('startBillingPortal', 'main', 'bus_owner', StartPortalCommand::class, StartPortalResult::class, secret: true);
    $lease = new IdempotencyLease('scope', 'key', 'fingerprint', 'first-owner', new DateTimeImmutable());
    $proof = ['organizationId' => 'verified-organization'];
    $receipts = new DoctrineIdempotentConsumerAdapter($write);
    $failedEvents = $this->createMock(EventDispatcherPort::class);
    $failedEvents->expects(self::once())->method('dispatch')->willThrowException(new RuntimeException('Interrupted durable enqueue'));

    try {
      new ExternalBillingReconciliationAdapter($write, $receipts, $failedEvents)->requestReconciliation($operation, $lease, $proof, ['stripe.portal']);
      self::fail('Enqueue failure must propagate.');
    } catch (RuntimeException $failure) {
      self::assertSame('Interrupted durable enqueue', $failure->getMessage());
    }
    self::assertSame(0, $observer->fetchOne('SELECT COUNT(*) FROM consumed_events'));
    self::assertSame(0, $receiver->getMessageCount());
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn('bf000000-0000-4000-8000-000000000001');
    $events = new TransactionalEventDispatcher($write, $sender, $ids, $this->createStub(CurrentActorPort::class));
    $adapter = new ExternalBillingReconciliationAdapter($write, $receipts, $events);
    $adapter->requestReconciliation($operation, $lease, $proof, ['stripe.portal']);
    $adapter->requestReconciliation($operation, new IdempotencyLease('scope', 'key', 'fingerprint', 'another-owner', new DateTimeImmutable()), $proof, ['stripe.portal']);
    self::assertSame(1, $observer->fetchOne('SELECT COUNT(*) FROM consumed_events'));
    self::assertSame(1, $receiver->getMessageCount());
    $envelopes = iterator_to_array($receiver->get());
    self::assertCount(1, $envelopes);
    $message = $envelopes[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(ExternalBillingReconciliationRequested::class, $message->event);
    self::assertSame('verified-organization', $message->event->organizationId);
    self::assertSame(['stripe.portal'], $message->event->purposes);
    $receiver->ack($envelopes[0]);

    $write->beginTransaction();

    try {
      $adapter->requestReconciliation($operation, $lease, $proof, ['stripe.portal']);
      self::fail('The failed owning transaction must have ended before reconciliation enqueue.');
    } catch (LogicException $failure) {
      self::assertStringContainsString('transaction has ended', $failure->getMessage());
    } finally {
      $write->rollBack();
    }
  }

  private function connection(): Connection
  {
    $url = $_ENV['MAIN_DATABASE_URL'];
    self::assertIsString($url);
    $connection = DriverManager::getConnection(['url' => $url]);
    $this->connections[] = $connection;

    return $connection;
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=billing_external_reconciliation_test&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }
}
