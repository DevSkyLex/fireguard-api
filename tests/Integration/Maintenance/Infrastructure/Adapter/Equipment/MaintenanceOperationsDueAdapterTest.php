<?php

declare(strict_types=1);

namespace Tests\Integration\Maintenance\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Contract\Plan\MaintenancePlanState;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\{MaintenanceEquipmentDirectoryPort, MaintenanceFacilityLifecyclePort};
use Maintenance\Infrastructure\Adapter\Equipment\MaintenanceOperationsDueAdapter;
use Maintenance\Infrastructure\Persistence\Doctrine\Repository\MaintenancePlanRepository;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Port\Outbound\ClockPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Real plan queries keep controls and servicing separate across tenant/lifecycle scopes. */
final class MaintenanceOperationsDueAdapterTest extends KernelTestCase
{
  private const string ORG = '780e8400-e29b-41d4-a716-446655447001';

  private const string EQ = '780e8400-e29b-41d4-a716-446655447002';

  private const string OTHER = '780e8400-e29b-41d4-a716-446655447003';

  #[Test]
  public function overdueServicingDoesNotMakeAControlOverdue(): void
  {
    [$adapter, $store] = $this->fixture('operational', false);
    $store->save($this->plan('control', self::EQ, new DateTimeImmutable('2027-01-01Z')));
    $store->save($this->plan('maintenance', self::EQ, new DateTimeImmutable('2026-10-01Z')));
    $result = $adapter->forEquipment(self::ORG, [self::EQ, self::OTHER]);
    self::assertArrayNotHasKey(self::OTHER, $result);
    self::assertSame('plans', $result[self::EQ]->engineMode);
    self::assertSame('up_to_date', $result[self::EQ]->controlDueStatus);
    self::assertSame('overdue', $result[self::EQ]->serviceDueStatus);
    self::assertSame('2027-01-01', $result[self::EQ]->controlNextDueAt?->format('Y-m-d'));
    self::assertSame('2026-10-01', $result[self::EQ]->serviceNextDueAt?->format('Y-m-d'));
  }

  #[Test]
  public function anUninitializedActiveOperationIsDueRatherThanUpToDate(): void
  {
    [$adapter, $store] = $this->fixture('operational', false);
    $plan = $this->plan('control', self::EQ, new DateTimeImmutable('2027-01-01Z'));
    $plan->nextDueAt = null;
    $store->save($plan);
    $result = $adapter->forEquipment(self::ORG, [self::EQ]);
    self::assertSame('overdue', $result[self::EQ]->controlDueStatus);
    self::assertNull($result[self::EQ]->controlNextDueAt);
    self::assertSame('unscheduled', $result[self::EQ]->serviceDueStatus);
  }

  #[Test]
  #[DataProvider('suspensions')]
  public function inactiveLifecycleDoesNotExposeFutureWorkAsScheduled(string $status, bool $archived): void
  {
    [$adapter, $store] = $this->fixture($status, $archived);
    $store->save($this->plan('control', self::EQ, new DateTimeImmutable('2027-01-01Z')));
    $store->save($this->plan('maintenance', self::EQ, new DateTimeImmutable('2026-10-01Z')));
    $result = $adapter->forEquipment(self::ORG, [self::EQ]);
    self::assertSame('unscheduled', $result[self::EQ]->controlDueStatus);
    self::assertSame('unscheduled', $result[self::EQ]->serviceDueStatus);
    self::assertNull($result[self::EQ]->controlNextDueAt);
    self::assertNull($result[self::EQ]->serviceNextDueAt);
    self::assertNotNull($store->find(self::ORG, $this->plan('control', self::EQ, new DateTimeImmutable('2027-01-01Z'))->id));
  }

  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function suspensions(): iterable
  {
    yield 'retired equipment' => ['decommissioned', false];
    yield 'archived site' => ['operational', true];
  }

  /**
   * @return array{MaintenanceOperationsDueAdapter, MaintenancePlanRepository}
   */
  private function fixture(string $status, bool $archived): array
  {
    self::bootKernel();
    $connection = static::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);
    $store = new MaintenancePlanRepository($connection);
    $now = new DateTimeImmutable('2026-10-06Z');
    $store->activateEngine(self::ORG, $now);
    $equipment = $this->createStub(MaintenanceEquipmentDirectoryPort::class);
    $equipment->method('findEquipmentByIds')->willReturn([new TrackableEquipment(self::EQ, self::ORG, null, 'fire_extinguisher', $status), new TrackableEquipment(self::OTHER, self::OTHER, null, 'fire_extinguisher', 'operational')]);
    $facilities = $this->createStub(MaintenanceFacilityLifecyclePort::class);
    $facilities->method('isArchived')->willReturn($archived);
    $policies = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $policies->method('compliancePolicy')->willReturn(new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P1Y'], 30));
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    return [new MaintenanceOperationsDueAdapter($connection, $equipment, $facilities, $policies, $clock), $store];
  }

  private function plan(string $kind, string $equipmentId, DateTimeImmutable $due): MaintenancePlanState
  {
    $now = new DateTimeImmutable('2026-10-06Z');
    $id = 'control' === $kind ? '780e8400-e29b-41d4-a716-446655447010' : '780e8400-e29b-41d4-a716-446655447011';

    return new MaintenancePlanState($id, self::ORG, $equipmentId, null, 'fire_extinguisher', $kind . ' operation', $kind, 'P1Y', 'fixed', $due, $due, true, null, null, null, $now, $now);
  }
}
