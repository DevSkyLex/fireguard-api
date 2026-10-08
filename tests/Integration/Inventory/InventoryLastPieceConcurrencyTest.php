<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection,DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager,EntityManagerInterface};
use Equipment\Application\Port\Inbound\EquipmentReserveReceiptPort;
use Intervention\Application\Contract\Inventory\InventoryInterventionContext;
use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use Inventory\Application\UseCase\Command\ApplyInventoryStock\{ApplyInventoryStockCommand,ApplyInventoryStockHandler};
use Inventory\Domain\Model\Stock\{InventoryReference,StockBalance};
use Inventory\Infrastructure\Adapter\Procurement\{InventoryPartDirectoryAdapter, InventoryStockReceiptAdapter};
use Inventory\Infrastructure\Persistence\Doctrine\Repository\InventoryRepository;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use MaintenanceCost\Infrastructure\Adapter\Currency\MaintenanceCurrencyAdapter;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use Procurement\Application\Service\ProcurementProjection;
use Procurement\Application\UseCase\Command\ManageProcurement\{ManageProcurementCommand, ManageProcurementHandler};
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\{ProcurementGoodsIdentity, ProcurementLine, SupplierDetails};
use Procurement\Infrastructure\Persistence\Doctrine\Repository\ProcurementRepository;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort,UuidGeneratorPort};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function str_pad;

use const STR_PAD_LEFT;

/** Two independent PostgreSQL sessions cannot consume the same last piece. @category Integration Tests */
#[SkipDatabaseRollback]
final class InventoryLastPieceConcurrencyTest extends KernelTestCase
{
  private const string ORG = 'bec10000-0000-4000-8000-000000000001';

  private const string PART = 'bec10000-0000-4000-8000-000000000002';

  private const string WAREHOUSE = 'bec10000-0000-4000-8000-000000000003';

  private EntityManagerInterface $main;

  private Connection $a;

  private Connection $b;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->main = $em;
    $this->a = $em->getConnection();
    $this->b = DriverManager::getConnection($this->a->getParams());
    $this->clean();
    self::assertNotSame($this->a->fetchOne('SELECT pg_backend_pid()'), $this->b->fetchOne('SELECT pg_backend_pid()'), 'The workers must use genuinely independent PostgreSQL sessions.');
    $store = new InventoryRepository($em->getConnection());
    $this->a->beginTransaction();
    $store->saveReference('parts', new InventoryReference(self::PART, self::ORG, 'LAST', 'Last piece', 'piece', 'part'));
    $store->saveReference('warehouses', new InventoryReference(self::WAREHOUSE, self::ORG, 'W', 'Warehouse'));
    $store->saveBalance(new StockBalance('bec10000-0000-4000-8000-000000000004', self::ORG, self::PART, self::WAREHOUSE, '1.000000', '7.000000', 'EUR'));
    $this->a->commit();
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $c) {
      while ($c->isTransactionActive()) {
        $c->rollBack();
      }
    }
    $this->clean();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function procurementReceptionAndConsumptionShareCurrencyBeforeIdentityAndReferenceLocks(): void
  {
    $other = new EntityManager($this->b, $this->main->getConfiguration());
    $procurement = $this->procurement($other);
    $receive = new ManageProcurementCommand('bec10000-0000-4000-8000-000000000005', self::ORG, 'receive', 'bec10000-0000-4000-8000-000000000101', 2, ['clientOperationId' => 'bec10000-0000-4000-8000-000000000103', 'lineId' => 'bec10000-0000-4000-8000-000000000102', 'warehouseId' => self::WAREHOUSE, 'quantity' => '1', 'receivedAt' => '2026-10-06T10:00:00Z']);
    $currency = new MaintenanceCurrencyAdapter($this->a);
    $interleavedCurrency = $this->createStub(MaintenanceCurrencyPort::class);
    $delivered = null;
    $interleavedCurrency->method('lock')->willReturnCallback(function (string $organizationId) use ($currency, $procurement, $receive, &$delivered): string {
      if (null === $delivered) {
        // Session B enters the real reception bridge while A is active, immediately before A's currency lock.
        $delivered = $procurement($receive);
        self::assertSame('stock_received', $delivered->data['status']);
        // A must not yet own its operation lock: Procurement already owns currency before entering Inventory.
        self::assertTrue($this->b->fetchOne('SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0))', ['inventory-operation:' . self::ORG . ':' . $this->consume(10)->clientOperationId]));
      }

      return $currency->lock($organizationId);
    });
    $workerA = $this->handler($this->main, $this->a, 10, $interleavedCurrency);
    $this->b->executeStatement("SET lock_timeout='150ms'");
    $this->a->executeStatement("SET lock_timeout='150ms'");
    $this->b->beginTransaction();

    try {
      $workerA($this->consume(10));
      self::fail('Consumption must serialize behind the reception transaction currency lock.');
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState(), 'The wait is bounded by the test, never resolved by a deadlock victim.');
    }
    self::assertNotNull($delivered, 'Reception must acquire references successfully before A acquires currency.');
    self::assertSame(0, $this->b->fetchOne('SELECT COUNT(*) FROM inventory_declarations WHERE organization_id=?', [self::ORG]));
    $this->b->commit();
    $consumed = $workerA($this->consume(10));
    self::assertNotNull($consumed->declaration);
    self::assertSame('confirmed', $consumed->declaration->status);
    self::assertTrue($workerA($this->consume(10))->replayed);
    self::assertTrue($procurement($receive)->replayed);
    self::assertSame('1.000000', $this->a->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id=?', [self::ORG]));
    self::assertSame('7.000000', $this->a->fetchOne('SELECT total_value FROM inventory_balances WHERE organization_id=?', [self::ORG]));
    self::assertSame(2, $this->a->fetchOne('SELECT COUNT(*) FROM inventory_movements WHERE organization_id=?', [self::ORG]));
    self::assertSame(2, $this->a->fetchOne('SELECT COUNT(*) FROM inventory_operation_receipts WHERE organization_id=?', [self::ORG]));
    self::assertSame(1, $this->a->fetchOne('SELECT COUNT(*) FROM procurement_receipts WHERE organization_id=?', [self::ORG]));
    self::assertSame(1, $this->a->fetchOne('SELECT COUNT(*) FROM procurement_operations WHERE organization_id=?', [self::ORG]));
  }

  #[Test]
  public function theSecondWorkerWaitsThenPersistsItsFullPendingDeclaration(): void
  {
    $workerA = $this->handler($this->main, $this->a, 10);
    $other = new EntityManager($this->b, $this->main->getConfiguration());
    $workerB = $this->handler($other, $this->b, 20);
    $this->a->beginTransaction();
    $first = $workerA($this->consume(10));
    self::assertNotNull($first->declaration);
    self::assertSame('confirmed', $first->declaration->status);
    $this->b->executeStatement("SET lock_timeout='150ms'");

    try {
      $workerB($this->consume(20));
      self::fail('The second worker must wait until the first complete stock issue commits.');
    } catch (DriverException $e) {
      self::assertSame('55P03', $e->getSQLState());
    }
    $this->a->commit();
    $pending = $workerB($this->consume(20));
    self::assertNotNull($pending->declaration);
    self::assertSame('received_pending', $pending->declaration->status);
    self::assertSame('insufficient_stock', $pending->declaration->reason);
    self::assertSame('1.000000', $pending->declaration->quantity);
    $replay = $workerB($this->consume(20));
    self::assertTrue($replay->replayed);
    self::assertSame('0.000000', $this->b->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id=?', [self::ORG]));
    self::assertSame(1, $this->b->fetchOne("SELECT COUNT(*) FROM inventory_movements WHERE organization_id=? AND kind='consumption'", [self::ORG]));
    self::assertSame(2, $this->b->fetchOne('SELECT COUNT(*) FROM inventory_declarations WHERE organization_id=?', [self::ORG]));
  }

  private function procurement(EntityManagerInterface $em): ManageProcurementHandler
  {
    $repository = new ProcurementRepository($this->b);
    $now = new DateTimeImmutable('2026-10-06T12:00:00Z');
    $supplier = Supplier::create('bec10000-0000-4000-8000-000000000100', self::ORG, new SupplierDetails('Concurrency supplier', null, null, null, []), $now);
    $order = PurchaseOrder::create('bec10000-0000-4000-8000-000000000101', self::ORG, $supplier->id, 'EUR', 'Concurrent receipt', [ProcurementLine::create('bec10000-0000-4000-8000-000000000102', new ProcurementGoodsIdentity('part', self::PART, null, []), '1', '7')], $now);
    $order->order(1, $now);
    $repository->saveSupplier($supplier);
    $repository->saveOrder($order);
    $inventory = $this->handler($em, $this->b, 30);
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturnCallback($inventory(...));
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $authorization->method('hasPermission')->willReturn(true);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn('bec10000-0000-4000-8000-000000000104');

    return new ManageProcurementHandler($repository, $authorization, new MaintenanceCurrencyAdapter($this->b), new InventoryPartDirectoryAdapter(new InventoryRepository($em->getConnection())), new InventoryStockReceiptAdapter($bus), $this->createStub(EquipmentReserveReceiptPort::class), new ProcurementProjection(), $clock, $ids, $this->createStub(EventDispatcherPort::class));
  }

  private function consume(int $n): ApplyInventoryStockCommand
  {
    return new ApplyInventoryStockCommand(self::ORG, 'bec10000-0000-4000-8000-000000000005', 'consumption', 'bec10000-0000-4000-8000-' . str_pad((string) $n, 12, '0', STR_PAD_LEFT), self::PART, self::WAREHOUSE, '1', new DateTimeImmutable('2026-10-06T10:00:00+00:00'), 'bec10000-0000-4000-8000-' . str_pad((string) ($n + 100), 12, '0', STR_PAD_LEFT));
  }

  private function handler(EntityManagerInterface $em, Connection $connection, int $counter, ?MaintenanceCurrencyPort $currency = null): ApplyInventoryStockHandler
  {
    $tx = $this->createStub(TransactionManagerPort::class);
    $tx->method('transactional')->willReturnCallback(static fn (callable $call): mixed => $connection->transactional(static fn (Connection $active): mixed => $call()));
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturnCallback(static function () use (&$counter): string {return 'bec20000-0000-4000-8000-' . str_pad((string) ++$counter, 12, '0', STR_PAD_LEFT); });
    $currency ??= new MaintenanceCurrencyAdapter($connection);
    $context = $this->createStub(InterventionInventoryContextPort::class);
    $context->method('validate')->willReturn(new InventoryInterventionContext(false));

    return new ApplyInventoryStockHandler(new InventoryRepository($em->getConnection()), $tx, $ids, $currency, $context);
  }

  private function clean(): void
  {
    foreach (['procurement_operations', 'procurement_receipts', 'procurement_orders', 'procurement_suppliers', 'inventory_operation_receipts', 'inventory_declarations', 'inventory_movements', 'inventory_balances', 'inventory_parts', 'inventory_warehouses', 'maintenance_cost_currency_settings'] as $table) {
      $this->a->executeStatement('DELETE FROM ' . $table . ' WHERE organization_id=?', [self::ORG]);
    }
    $this->main->clear();
  }
}
