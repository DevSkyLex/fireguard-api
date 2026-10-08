<?php

declare(strict_types=1);

namespace Tests\Integration\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Procurement\Application\Contract\{ProcurementOperationState, ProcurementReceiptState};
use Procurement\Domain\Event\ProcurementChangedEvent;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\{ProcurementGoodsIdentity, ProcurementLine, SupplierDetails};
use Procurement\Infrastructure\Persistence\Doctrine\Repository\ProcurementRepository;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Shared\Infrastructure\Messaging\Outbox\TransactionalEventDispatcher;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{DoctrineTransport, DoctrineTransportFactory};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function array_map;

/** Independent PostgreSQL connections prove retained physical evidence and last-quantity exclusion. */
final class ProcurementRepositoryTest extends TestCase
{
  private const string ORG = '790e8400-e29b-41d4-a716-446655447001';

  private const string FOREIGN = '790e8400-e29b-41d4-a716-446655447002';

  private const string SUPPLIER = '790e8400-e29b-41d4-a716-446655447003';

  private const string ORDER = '790e8400-e29b-41d4-a716-446655447004';

  private const string LINE = '790e8400-e29b-41d4-a716-446655447005';

  private const string PART = '790e8400-e29b-41d4-a716-446655447006';

  private const string RECEIPT = '790e8400-e29b-41d4-a716-446655447007';

  private const string OPERATION = '790e8400-e29b-41d4-a716-446655447008';

  private const string QUEUE = 'procurement_atomicity';

  private Connection $a;

  private Connection $b;

  private ProcurementRepository $repository;

  private DoctrineTransport $sender;

  private DoctrineTransport $receiver;

  protected function setUp(): void
  {
    $url = $_ENV['MAIN_DATABASE_URL'] ?? $_SERVER['MAIN_DATABASE_URL'] ?? null;
    self::assertIsString($url);
    $this->a = DriverManager::getConnection(['url' => $url]);
    $this->b = DriverManager::getConnection(['url' => $url]);
    $this->repository = new ProcurementRepository($this->a);
    $this->sender = $this->transport($this->a);
    $this->receiver = $this->transport($this->b);
    $this->sender->setup();
    $this->clean();
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clean();
    $this->a->close();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function creationResourcesAndReplayIdentitiesCommitOrRollbackTogether(): void
  {
    $supplier = Supplier::create(self::SUPPLIER, self::ORG, new SupplierDetails('Creation retry supplier', null, null, null, []), $this->now());
    $order = PurchaseOrder::create(self::ORDER, self::ORG, self::SUPPLIER, 'EUR', 'Creation retry draft', [ProcurementLine::create(self::LINE, new ProcurementGoodsIdentity('part', self::PART, null, []), '1', null)], $this->now());
    $write = function () use ($supplier, $order): void {
      $this->repository->saveSupplier($supplier);
      $this->repository->saveOrder($order);
      $this->repository->saveOperation(new ProcurementOperationState(self::ORG, self::OPERATION, 'create_supplier', 'supplier-fingerprint', self::SUPPLIER));
      $this->repository->saveOperation(new ProcurementOperationState(self::ORG, self::RECEIPT, 'create_order', 'order-fingerprint', self::ORDER));
      $other = new ProcurementRepository($this->b);
      self::assertNull($other->supplier(self::ORG, self::SUPPLIER));
      self::assertNull($other->order(self::ORG, self::ORDER));
      self::assertNull($other->operation(self::ORG, self::OPERATION));
      self::assertNull($other->operation(self::ORG, self::RECEIPT));
    };

    try {
      $this->repository->synchronized(self::ORG, static function () use ($write): void {
        $write();

        throw new RuntimeException('Fail before the creation transaction commits.');
      });
    } catch (RuntimeException $exception) {
      self::assertSame('Fail before the creation transaction commits.', $exception->getMessage());
    }
    self::assertNull($this->repository->supplier(self::ORG, self::SUPPLIER));
    self::assertNull($this->repository->order(self::ORG, self::ORDER));
    self::assertNull($this->repository->operation(self::ORG, self::OPERATION));
    self::assertNull($this->repository->operation(self::ORG, self::RECEIPT));
    $this->repository->synchronized(self::ORG, $write);
    $other = new ProcurementRepository($this->b);
    self::assertSame(self::SUPPLIER, $other->supplier(self::ORG, self::SUPPLIER)?->id);
    self::assertSame(self::ORDER, $other->order(self::ORG, self::ORDER)?->id);
    self::assertSame(self::SUPPLIER, $other->operation(self::ORG, self::OPERATION)?->receiptId);
    self::assertSame(self::ORDER, $other->operation(self::ORG, self::RECEIPT)?->receiptId);
  }

  #[Test]
  public function exactQuantitiesCostsAndMotivatedDeclarationsRoundTrip(): void
  {
    $order = $this->order('2.500000');
    $order->recordReceipt(2, self::LINE, '1.250000', $this->now());
    $this->repository->saveOrder($order);
    $receipt = $this->receipt('1.250000');
    $this->repository->saveReceipt($receipt);
    $this->repository->saveOperation(new ProcurementOperationState(self::ORG, self::OPERATION, 'return', 'fingerprint', self::RECEIPT, ['quantity' => '0.250000', 'reason' => 'Packaging damaged', 'actorId' => self::SUPPLIER]));
    $other = new ProcurementRepository($this->b);
    $read = $other->order(self::ORG, self::ORDER);
    self::assertNotNull($read);
    self::assertSame('1.250000', $read->lines()[0]->remainingQuantity());
    self::assertSame('4.250000', $read->lines()[0]->unitCost);
    $readReceipt = $other->receipt(self::ORG, self::RECEIPT);
    self::assertNotNull($readReceipt);
    self::assertSame('1.250000', $readReceipt->quantity);
    self::assertSame('4.250000', $readReceipt->unitCost);
    self::assertSame('Packaging damaged', $other->operation(self::ORG, self::OPERATION)?->declaration['reason']);
  }

  #[Test]
  public function theLastQuantityIsSerializedAndAStaleSecondWorkerCannotReceiveIt(): void
  {
    $this->repository->saveOrder($this->order());
    $this->a->beginTransaction();
    $this->repository->synchronized(self::ORG, function (): void {
      $order = $this->repository->order(self::ORG, self::ORDER);
      self::assertNotNull($order);
      $order->recordReceipt(2, self::LINE, '1.000000', $this->now());
      $this->repository->saveOrder($order);
      $this->repository->saveReceipt($this->receipt());
    });
    $this->b->beginTransaction();
    $this->b->executeStatement("SET LOCAL lock_timeout = '40ms'");

    try {
      new ProcurementRepository($this->b)->synchronized(self::ORG, static fn (): bool => true);
      self::fail('The other worker must wait for the organization transaction.');
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState());
    }
    $this->b->rollBack();
    $this->a->commit();

    try {
      new ProcurementRepository($this->b)->synchronized(self::ORG, function (): void {
        $order = new ProcurementRepository($this->b)->order(self::ORG, self::ORDER);
        self::assertNotNull($order);
        $order->recordReceipt(2, self::LINE, '1.000000', $this->now());
      });
      self::fail('The second worker must see the changed revision.');
    } catch (ProcurementException $exception) {
      self::assertSame('stale', $exception->errorCode);
    }
    $final = new ProcurementRepository($this->b)->order(self::ORG, self::ORDER);
    self::assertNotNull($final);
    self::assertSame('received', $final->status()->value);
    self::assertSame('0.000000', $final->lines()[0]->remainingQuantity());
    self::assertSame(1, $this->b->fetchOne('SELECT COUNT(*) FROM procurement_receipts WHERE organization_id = ?', [self::ORG]));
  }

  #[Test]
  public function orderPhysicalReceiptOperationAndOutboxCommitOrRollbackTogether(): void
  {
    $ids = $this->createStub(UuidFactory::class);
    $ids->method('generateRaw')->willReturn(self::OPERATION);
    $dispatcher = new TransactionalEventDispatcher($this->a, $this->sender, $ids, $this->createStub(CurrentActorPort::class));
    $write = function () use ($dispatcher): void {
      $this->repository->saveOrder($this->order());
      $this->repository->saveReceipt($this->receipt());
      $this->repository->saveOperation(new ProcurementOperationState(self::ORG, self::OPERATION, 'receive', 'fingerprint', self::RECEIPT));
      $dispatcher->dispatch(new ProcurementChangedEvent(self::ORG, self::RECEIPT, 'receive', $this->now()));
      self::assertNull(new ProcurementRepository($this->b)->receipt(self::ORG, self::RECEIPT));
      self::assertSame(0, $this->receiver->getMessageCount());
    };

    try {
      $this->repository->synchronized(self::ORG, function () use ($write): void {
        $write();

        throw new RuntimeException('Simulate stock confirmation failure');
      });
    } catch (RuntimeException $exception) {
      self::assertSame('Simulate stock confirmation failure', $exception->getMessage());
    }
    self::assertNull(new ProcurementRepository($this->b)->order(self::ORG, self::ORDER));
    self::assertNull(new ProcurementRepository($this->b)->receipt(self::ORG, self::RECEIPT));
    self::assertNull(new ProcurementRepository($this->b)->operation(self::ORG, self::OPERATION));
    self::assertSame(0, $this->receiver->getMessageCount());
    $this->repository->synchronized(self::ORG, $write);
    self::assertNotNull(new ProcurementRepository($this->b)->receipt(self::ORG, self::RECEIPT));
    self::assertSame(1, $this->receiver->getMessageCount());
  }

  #[Test]
  public function foreignIdentifiersAreNotLoadedAndUnrelatedOrganizationsDoNotBlock(): void
  {
    $this->repository->saveSupplier(Supplier::create(self::SUPPLIER, self::ORG, new SupplierDetails('Supplier', null, null, null, []), $this->now()));
    $this->repository->saveOrder($this->order());
    $this->repository->saveReceipt($this->receipt());
    $other = new ProcurementRepository($this->b);
    self::assertNull($other->supplier(self::FOREIGN, self::SUPPLIER));
    self::assertNull($other->order(self::FOREIGN, self::ORDER));
    self::assertNull($other->receipt(self::FOREIGN, self::RECEIPT));
    self::assertSame([], $other->orders(self::FOREIGN, null, null, 0, 100));
    $this->a->beginTransaction();
    $this->repository->synchronized(self::ORG, static fn (): bool => true);
    $independent = false;
    $other->synchronized(self::FOREIGN, static function () use (&$independent): void { $independent = true; });
    self::assertTrue($independent);
    $this->a->rollBack();
  }

  /**
   * Proves the nullable archive predicate preserves search, scope, count and paging in PostgreSQL.
   */
  #[Test]
  public function allSupplierSelectionRetainsActiveAndArchivedWithExactPagedCount(): void
  {
    $active = Supplier::create(self::SUPPLIER, self::ORG, new SupplierDetails('Safety active', null, null, null, []), $this->now());
    $archived = Supplier::create(self::ORDER, self::ORG, new SupplierDetails('Safety archived', null, null, null, []), $this->now());
    $archived->archive(1, $this->now());
    $this->repository->saveSupplier($active);
    $this->repository->saveSupplier($archived);
    $this->repository->saveSupplier(Supplier::create(self::LINE, self::ORG, new SupplierDetails('Unrelated', null, null, null, []), $this->now()));

    self::assertSame([self::SUPPLIER], array_map(static fn (Supplier $supplier): string => $supplier->id, $this->repository->suppliers(self::ORG, 'Safety', false, 0, 30)));
    self::assertSame([self::ORDER], array_map(static fn (Supplier $supplier): string => $supplier->id, $this->repository->suppliers(self::ORG, 'Safety', true, 0, 30)));
    self::assertSame(2, $this->repository->countSuppliers(self::ORG, 'Safety', null));
    self::assertCount(1, $this->repository->suppliers(self::ORG, 'Safety', null, 1, 1));
    self::assertCount(2, $this->repository->suppliers(self::ORG, 'Safety', null, 0, 30));
    self::assertSame([], $this->repository->suppliers(self::FOREIGN, 'Safety', null, 0, 30));
    self::assertSame(0, $this->repository->countSuppliers(self::FOREIGN, 'Safety', null));
  }

  private function order(string $quantity = '1.000000'): PurchaseOrder
  {
    $order = PurchaseOrder::create(self::ORDER, self::ORG, self::SUPPLIER, 'EUR', 'Purchase', [ProcurementLine::create(self::LINE, new ProcurementGoodsIdentity('part', self::PART, null, []), $quantity, '4.250000')], $this->now());
    $order->order(1, $this->now());

    return $order;
  }

  private function receipt(string $quantity = '1.000000'): ProcurementReceiptState
  {
    return new ProcurementReceiptState(self::RECEIPT, self::ORG, self::ORDER, self::LINE, 'part', $quantity, self::PART, '4.250000', 'EUR', $this->now(), self::SUPPLIER, $this->now());
  }

  private function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-06T00:00:00Z');
  }

  private function transport(Connection $connection): DoctrineTransport
  {
    $registry = $this->createStub(ConnectionRegistry::class);
    $registry->method('getConnection')->willReturn($connection);
    $transport = new DoctrineTransportFactory($registry)->createTransport('doctrine://main?queue_name=' . self::QUEUE . '&auto_setup=false', ['use_notify' => false], new PhpSerializer());
    self::assertInstanceOf(DoctrineTransport::class, $transport);

    return $transport;
  }

  private function clean(): void
  {
    $this->a->delete('messenger_messages', ['queue_name' => self::QUEUE]);
    foreach (['procurement_operations', 'procurement_returns', 'procurement_receipts', 'procurement_orders', 'procurement_suppliers'] as $table) {
      $this->a->delete($table, ['organization_id' => self::ORG]);
    }
  }
}
