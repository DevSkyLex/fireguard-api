<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Workflow;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Intervention\Application\Contract\Workflow\InterventionWorkflowMutation;
use Intervention\Application\Port\Outbound\{InterventionResourceGatewayPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Infrastructure\Adapter\Cost\InterventionCostSourceFactsAdapter;
use Intervention\Infrastructure\Adapter\Inventory\InterventionInventoryContextAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionWorkItemRecord};
use Inventory\Application\UseCase\Command\ApplyInventoryStock\{ApplyInventoryStockCommand, ApplyInventoryStockHandler};
use Inventory\Domain\Model\Stock\{InventoryReference, StockBalance};
use Inventory\Infrastructure\Adapter\Intervention\InventoryInterventionResourcesAdapter;
use Inventory\Infrastructure\Persistence\Doctrine\Repository\InventoryRepository;
use MaintenanceCost\Application\Service\{MaintenanceCostAccessGuard, MaintenanceCostProjection};
use MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\{WriteMaintenanceCostCommand, WriteMaintenanceCostHandler};
use MaintenanceCost\Domain\Service\MaintenanceCostCalculator;
use MaintenanceCost\Infrastructure\Adapter\Currency\MaintenanceCurrencyAdapter;
use MaintenanceCost\Infrastructure\Adapter\Rate\MaintenanceRateAdapter;
use MaintenanceCost\Infrastructure\Persistence\Doctrine\Repository\MaintenanceCostRepository;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Port\Outbound\{ClockPort, UuidGeneratorPort};
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class InterventionRetentionConcurrencyTest
 *
 * Uses real owner writers and independent PostgreSQL sessions to prove deletion
 * waits for uncommitted expense and stock facts before checking retention.
 *
 * @category Integration Tests
 */
#[SkipDatabaseRollback]
final class InterventionRetentionConcurrencyTest extends KernelTestCase
{
  private const string ORG = 'bec50000-0000-4000-8000-000000000001';

  private const string USER = 'bec50000-0000-4000-8000-000000000002';

  private const string WORK = 'bec50000-0000-4000-8000-000000000003';

  private const string TASK = 'bec50000-0000-4000-8000-000000000004';

  private const string PART = 'bec50000-0000-4000-8000-000000000005';

  private const string WAREHOUSE = 'bec50000-0000-4000-8000-000000000006';

  private EntityManagerInterface $main;

  private EntityManagerInterface $writer;

  private Connection $a;

  private Connection $b;

  protected function setUp(): void
  {
    self::bootKernel();
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $this->main = $main;
    $this->a = $main->getConnection();
    $this->b = DriverManager::getConnection($this->a->getParams());
    $this->writer = new EntityManager($this->b, $main->getConfiguration());
    $this->clean();
    self::assertNotSame($this->a->fetchOne('SELECT pg_backend_pid()'), $this->b->fetchOne('SELECT pg_backend_pid()'));
    $now = new DateTimeImmutable('2026-10-01T00:00:00Z');
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Retention concurrency';
    $organization->slug = 'retention-concurrency';
    $organization->ownerUserId = $organization->createdByUserId = self::USER;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $organization->updatedAt = $now;
    $main->persist($organization);
    $member = new OrganizationMemberRecord();
    $member->id = 'bec50000-0000-4000-8000-000000000007';
    $member->organization = $organization;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = $now;
    $main->persist($member);
    $role = new OrganizationRoleRecord();
    $role->id = 'bec50000-0000-4000-8000-000000000010';
    $role->organization = $organization;
    $role->name = 'retention-owner';
    $role->permissions = ['*'];
    $role->isSystem = false;
    $role->createdAt = $now;
    $main->persist($role);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $main->persist($assignment);
    $work = new InterventionRecord();
    $work->id = self::WORK;
    $work->organization = $organization;
    $work->name = 'Prepared retained work';
    $work->number = 1;
    $work->type = 'corrective_maintenance';
    $work->status = 'draft';
    $work->responsibleId = $member->id;
    $work->createdAt = $work->updatedAt = $now;
    $main->persist($work);
    $task = new InterventionWorkItemRecord();
    $task->id = self::TASK;
    $task->intervention = $work;
    $task->action = 'inventory';
    $task->status = 'planned';
    $task->source = 'planned';
    $task->createdAt = $task->updatedAt = $now;
    $main->persist($task);
    $main->flush();
    $store = new InventoryRepository($main->getConnection());
    $this->a->transactional(function () use ($store): void {
      $store->saveReference('parts', new InventoryReference(self::PART, self::ORG, 'RET', 'Retained part', 'piece', 'part'));
      $store->saveReference('warehouses', new InventoryReference(self::WAREHOUSE, self::ORG, 'RET', 'Retained warehouse'));
      $store->saveBalance(new StockBalance('bec50000-0000-4000-8000-000000000008', self::ORG, self::PART, self::WAREHOUSE, '1.000000', '7.000000', 'EUR'));
    });
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->a->executeStatement("SET lock_timeout = '0'");
    $this->clean();
    $this->b->close();
    parent::tearDown();
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function ownerScopes(): iterable
  {
    yield 'expense retains parent' => ['expense', false];
    yield 'expense retains task' => ['expense', true];
    yield 'stock retains parent' => ['stock', false];
    yield 'stock retains task' => ['stock', true];
  }

  #[Test]
  #[DataProvider('ownerScopes')]
  public function deletionWaitsForTheOwnerWriteThenRetainsCommittedFacts(string $kind, bool $taskScope): void
  {
    $this->b->beginTransaction();
    $work = new InterventionCostSourceFactsAdapter($this->writer);
    $currency = new MaintenanceCurrencyAdapter($this->b);
    $stock = new InventoryRepository($this->writer->getConnection());
    $inventory = new InventoryInterventionResourcesAdapter($stock, $currency);
    $costs = new MaintenanceCostRepository($this->writer);
    $calculator = new MaintenanceCostCalculator();
    $projection = new MaintenanceCostProjection($work, $inventory, $currency, new MaintenanceRateAdapter($this->b), $costs, $calculator);
    $transactions = new DoctrineTransactionManagerAdapter($this->writer);
    /** @var UuidGeneratorPort $ids */
    $ids = self::getContainer()->get(UuidGeneratorPort::class);
    if ('expense' === $kind) {
      /** @var MaintenanceCostAccessGuard $access */
      $access = self::getContainer()->get(MaintenanceCostAccessGuard::class);
      /** @var ClockPort $clock */
      $clock = self::getContainer()->get(ClockPort::class);
      $handler = new WriteMaintenanceCostHandler($access, $work, $costs, $currency, $projection, $calculator, $transactions, $ids, $clock);
      $handler(new WriteMaintenanceCostCommand(self::USER, self::ORG, self::WORK, 'expense', ['clientId' => 'retention-expense', 'amount' => '7', 'description' => 'Retained specialist work', 'incurredAt' => '2026-10-01T10:00:00Z', 'workItemId' => $taskScope ? self::TASK : null]));
    } else {
      /** @var OrganizationAuthorizationPort $authorization */
      $authorization = self::getContainer()->get(OrganizationAuthorizationPort::class);
      /** @var InterventionResourceGatewayPort $resources */
      $resources = self::getContainer()->get(InterventionResourceGatewayPort::class);
      /** @var InterventionMemberPolicy $members */
      $members = self::getContainer()->get(InterventionMemberPolicy::class);
      $context = new InterventionInventoryContextAdapter($this->writer, $authorization, $resources, $members);
      $handler = new ApplyInventoryStockHandler($stock, $transactions, $ids, $currency, $context);
      $result = $handler(new ApplyInventoryStockCommand(self::ORG, self::USER, 'consumption', 'bec50000-0000-4000-8000-000000000009', self::PART, self::WAREHOUSE, '1', new DateTimeImmutable('2026-10-01T10:00:00Z'), self::WORK, $taskScope ? self::TASK : null));
      self::assertNotNull($result->declaration);
      self::assertSame('confirmed', $result->declaration->status);
    }
    /** @var InterventionWorkflowGatewayPort $gateway */
    $gateway = self::getContainer()->get(InterventionWorkflowGatewayPort::class);
    $delete = new InterventionWorkflowMutation(resource: $taskScope ? 'work_item' : 'intervention', action: 'delete', userId: self::USER, id: $taskScope ? self::TASK : self::WORK, payload: [], expectedRevision: 1);
    $this->a->executeStatement("SET lock_timeout = '150ms'");

    try {
      $gateway->mutate($delete);
      self::fail('Deletion must wait for the owner transaction instead of passing an empty history check.');
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState());
    }
    $this->b->commit();
    $this->a->executeStatement("SET lock_timeout = '0'");

    try {
      $gateway->mutate($delete);
      self::fail('The committed owner fact must retain its operational context.');
    } catch (InterventionConflictException $exception) {
      self::assertStringContainsString('history must be retained', $exception->getMessage());
    }
    self::assertSame(1, $this->a->fetchOne('SELECT COUNT(*) FROM interventions WHERE id = :id', ['id' => self::WORK]));
    self::assertSame(1, $this->a->fetchOne('SELECT COUNT(*) FROM intervention_work_items WHERE id = :id', ['id' => self::TASK]));
    self::assertSame('7.000000', $projection->view(self::ORG, self::WORK)->current->total);
    if ('stock' === $kind) {
      self::assertSame('0.000000', $this->a->fetchOne('SELECT quantity FROM inventory_balances WHERE organization_id = :org', ['org' => self::ORG]));
      self::assertSame(1, $this->a->fetchOne('SELECT COUNT(*) FROM inventory_declarations WHERE organization_id = :org', ['org' => self::ORG]));
      self::assertSame(1, $this->a->fetchOne("SELECT COUNT(*) FROM inventory_movements WHERE organization_id = :org AND kind = 'consumption'", ['org' => self::ORG]));
    } else {
      self::assertCount(1, $costs->expenses(self::ORG, self::WORK));
    }
  }

  private function clean(): void
  {
    foreach (['maintenance_cost_expenses', 'maintenance_cost_planning', 'maintenance_cost_snapshots', 'maintenance_cost_currency_settings', 'inventory_operation_receipts', 'inventory_declarations', 'inventory_movements', 'inventory_balances', 'inventory_parts', 'inventory_warehouses'] as $table) {
      $this->a->delete($table, ['organization_id' => self::ORG]);
    }
    $this->a->delete('organizations', ['id' => self::ORG]);
    $this->main->clear();
  }
}
