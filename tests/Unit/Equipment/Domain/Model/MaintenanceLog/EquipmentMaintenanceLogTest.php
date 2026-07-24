<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Model\MaintenanceLog;

use DateTimeImmutable;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId, MaintenanceLogSource};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(EquipmentMaintenanceLog::class)]
final class EquipmentMaintenanceLogTest extends TestCase
{
  private const string LOG_ID = '550e8400-e29b-41d4-a716-446655480001';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655480002';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655480003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655480004';

  #[Test]
  public function testOpenDefaultsToStatusTransitionSourceAndIsOngoing(): void
  {
    $log = EquipmentMaintenanceLog::open(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertSame(MaintenanceLogSource::STATUS_TRANSITION, $log->source());
    self::assertTrue($log->isOngoing());
    self::assertNull($log->completedAt());
    self::assertNull($log->interventionId());
    self::assertNull($log->interventionNumber());
    self::assertNull($log->workItemAction());
    self::assertNull($log->actorId());
    self::assertNull($log->summary());
  }

  #[Test]
  public function testCloseStampsCompletedAt(): void
  {
    $log = EquipmentMaintenanceLog::open(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    $log->close();

    self::assertFalse($log->isOngoing());
    self::assertNotNull($log->completedAt());
  }

  #[Test]
  public function testRecordInterventionServiceProducesACompletedPointInTimeEntry(): void
  {
    $occurredAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');

    $log = EquipmentMaintenanceLog::recordInterventionService(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      occurredAt: $occurredAt,
      interventionId: self::INTERVENTION_ID,
      interventionNumber: 42,
      workItemAction: 'status_change',
      actorId: '550e8400-e29b-41d4-a716-446655480005',
      summary: 'Replaced detector',
    );

    self::assertSame(MaintenanceLogSource::INTERVENTION, $log->source());
    self::assertFalse($log->isOngoing());
    self::assertSame($occurredAt, $log->startedAt());
    self::assertSame($occurredAt, $log->completedAt());
    self::assertSame(self::INTERVENTION_ID, $log->interventionId());
    self::assertSame(42, $log->interventionNumber());
    self::assertSame('status_change', $log->workItemAction());
    self::assertSame('550e8400-e29b-41d4-a716-446655480005', $log->actorId());
    self::assertSame('Replaced detector', $log->summary());
  }

  #[Test]
  public function testRecordInterventionServiceAllowsANullActorAndSummary(): void
  {
    $occurredAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');

    $log = EquipmentMaintenanceLog::recordInterventionService(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      occurredAt: $occurredAt,
      interventionId: self::INTERVENTION_ID,
      interventionNumber: 7,
      workItemAction: 'update',
      actorId: null,
    );

    self::assertNull($log->actorId());
    self::assertNull($log->summary());
  }

  #[Test]
  public function testReconstituteRoundTripsAllFields(): void
  {
    $startedAt = new DateTimeImmutable('2026-07-01T08:00:00+00:00');
    $completedAt = new DateTimeImmutable('2026-07-01T08:00:00+00:00');

    $log = EquipmentMaintenanceLog::reconstitute(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      startedAt: $startedAt,
      completedAt: $completedAt,
      source: MaintenanceLogSource::INTERVENTION,
      interventionId: self::INTERVENTION_ID,
      interventionNumber: 3,
      workItemAction: 'inventory',
      actorId: '550e8400-e29b-41d4-a716-446655480006',
      summary: 'Inventory pass',
    );

    self::assertSame(self::LOG_ID, (string) $log->id());
    self::assertSame(self::EQUIPMENT_ID, (string) $log->equipmentId());
    self::assertSame(self::ORGANIZATION_ID, (string) $log->organizationId());
    self::assertSame($startedAt, $log->startedAt());
    self::assertSame($completedAt, $log->completedAt());
    self::assertSame(MaintenanceLogSource::INTERVENTION, $log->source());
    self::assertSame(self::INTERVENTION_ID, $log->interventionId());
    self::assertSame(3, $log->interventionNumber());
    self::assertSame('inventory', $log->workItemAction());
    self::assertSame('550e8400-e29b-41d4-a716-446655480006', $log->actorId());
    self::assertSame('Inventory pass', $log->summary());
  }

  #[Test]
  public function testReconstituteDefaultsToStatusTransitionSourceWhenOmitted(): void
  {
    $startedAt = new DateTimeImmutable('2026-07-01T08:00:00+00:00');

    $log = EquipmentMaintenanceLog::reconstitute(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      startedAt: $startedAt,
      completedAt: null,
    );

    self::assertSame(MaintenanceLogSource::STATUS_TRANSITION, $log->source());
    self::assertTrue($log->isOngoing());
  }
}
