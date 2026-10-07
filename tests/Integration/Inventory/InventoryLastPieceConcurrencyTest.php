<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection,DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager,EntityManagerInterface};
use Intervention\Application\Contract\Inventory\InventoryInterventionContext;
use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use Inventory\Application\UseCase\Command\ApplyInventoryStock\{ApplyInventoryStockCommand,ApplyInventoryStockHandler};
use Inventory\Domain\Model\Stock\{InventoryReference,StockBalance};
use Inventory\Infrastructure\Persistence\Doctrine\Repository\InventoryRepository;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\{TransactionManagerPort,UuidGeneratorPort};
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
    $store = new InventoryRepository($em);
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

  private function consume(int $n): ApplyInventoryStockCommand
  {
    return new ApplyInventoryStockCommand(self::ORG, 'bec10000-0000-4000-8000-000000000005', 'consumption', 'bec10000-0000-4000-8000-' . str_pad((string) $n, 12, '0', STR_PAD_LEFT), self::PART, self::WAREHOUSE, '1', new DateTimeImmutable('2026-10-06T10:00:00+00:00'), 'bec10000-0000-4000-8000-' . str_pad((string) ($n + 100), 12, '0', STR_PAD_LEFT));
  }

  private function handler(EntityManagerInterface $em, Connection $connection, int $counter): ApplyInventoryStockHandler
  {
    $tx = $this->createStub(TransactionManagerPort::class);
    $tx->method('transactional')->willReturnCallback(static fn (callable $call): mixed => $connection->transactional(static fn (Connection $active): mixed => $call()));
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturnCallback(static function () use (&$counter): string {return 'bec20000-0000-4000-8000-' . str_pad((string) ++$counter, 12, '0', STR_PAD_LEFT); });
    $currency = $this->createStub(MaintenanceCurrencyPort::class);
    $currency->method('lock')->willReturn('EUR');
    $currency->method('forOrganization')->willReturn('EUR');
    $context = $this->createStub(InterventionInventoryContextPort::class);
    $context->method('validate')->willReturn(new InventoryInterventionContext(false));

    return new ApplyInventoryStockHandler(new InventoryRepository($em), $tx, $ids, $currency, $context);
  }

  private function clean(): void
  {
    foreach (['inventory_operation_receipts', 'inventory_declarations', 'inventory_movements', 'inventory_balances', 'inventory_parts', 'inventory_warehouses'] as $table) {
      $this->a->executeStatement('DELETE FROM ' . $table . ' WHERE organization_id=?', [self::ORG]);
    }
    $this->main->clear();
  }
}
