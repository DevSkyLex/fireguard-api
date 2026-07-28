<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId, MaintenanceLogSource};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\MaintenanceLogMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentMaintenanceLogRecord, EquipmentRecord};
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(MaintenanceLogMapper::class)]
final class MaintenanceLogMapperTest extends TestCase
{
  private const string LOG_ID = '550e8400-e29b-41d4-a716-446655481001';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655481002';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655481003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655481004';

  #[Test]
  public function testToDomainMapsIntervationServiceHistoryColumns(): void
  {
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;

    $record = new EquipmentMaintenanceLogRecord();
    $record->id = self::LOG_ID;
    $record->equipment = $equipment;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->startedAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');
    $record->completedAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');
    $record->source = 'intervention';
    $record->interventionId = self::INTERVENTION_ID;
    $record->interventionNumber = 5;
    $record->workItemAction = 'status_change';
    $record->actorId = '550e8400-e29b-41d4-a716-446655481005';
    $record->summary = 'Replaced detector';

    $log = MaintenanceLogMapper::toDomain($record);

    self::assertSame(MaintenanceLogSource::INTERVENTION, $log->source());
    self::assertSame(self::INTERVENTION_ID, $log->interventionId());
    self::assertSame(5, $log->interventionNumber());
    self::assertSame('status_change', $log->workItemAction());
    self::assertSame('550e8400-e29b-41d4-a716-446655481005', $log->actorId());
    self::assertSame('Replaced detector', $log->summary());
  }

  #[Test]
  public function testToDomainDefaultsToStatusTransitionSource(): void
  {
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;

    $record = new EquipmentMaintenanceLogRecord();
    $record->id = self::LOG_ID;
    $record->equipment = $equipment;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->startedAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');

    $log = MaintenanceLogMapper::toDomain($record);

    self::assertSame(MaintenanceLogSource::STATUS_TRANSITION, $log->source());
    self::assertNull($log->interventionId());
    self::assertNull($log->interventionNumber());
    self::assertNull($log->workItemAction());
    self::assertNull($log->actorId());
    self::assertNull($log->summary());
  }

  #[Test]
  public function testToRecordMapsIntervationServiceHistoryColumns(): void
  {
    $occurredAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');

    $log = EquipmentMaintenanceLog::recordInterventionService(
      id: MaintenanceLogId::fromString(self::LOG_ID),
      equipmentId: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      occurredAt: $occurredAt,
      interventionId: self::INTERVENTION_ID,
      interventionNumber: 9,
      workItemAction: 'update',
      actorId: '550e8400-e29b-41d4-a716-446655481006',
      summary: 'Inventory pass',
    );

    $record = MaintenanceLogMapper::toRecord($log);

    self::assertSame('intervention', $record->source);
    self::assertSame(self::INTERVENTION_ID, $record->interventionId);
    self::assertSame(9, $record->interventionNumber);
    self::assertSame('update', $record->workItemAction);
    self::assertSame('550e8400-e29b-41d4-a716-446655481006', $record->actorId);
    self::assertSame('Inventory pass', $record->summary);
    self::assertNull($record->dedupKey);
  }

  #[Test]
  public function testToDomainAndToRecordRoundTripIntervationServiceHistoryColumns(): void
  {
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;

    $record = new EquipmentMaintenanceLogRecord();
    $record->id = self::LOG_ID;
    $record->equipment = $equipment;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->startedAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');
    $record->completedAt = new DateTimeImmutable('2026-07-10T12:00:00+00:00');
    $record->source = 'intervention';
    $record->interventionId = self::INTERVENTION_ID;
    $record->interventionNumber = 5;
    $record->workItemAction = 'status_change';
    $record->actorId = '550e8400-e29b-41d4-a716-446655481005';
    $record->summary = 'Replaced detector';

    $log = MaintenanceLogMapper::toDomain($record);
    $roundTripped = MaintenanceLogMapper::toRecord($log);

    self::assertSame($record->source, $roundTripped->source);
    self::assertSame($record->interventionId, $roundTripped->interventionId);
    self::assertSame($record->interventionNumber, $roundTripped->interventionNumber);
    self::assertSame($record->workItemAction, $roundTripped->workItemAction);
    self::assertSame($record->actorId, $roundTripped->actorId);
    self::assertSame($record->summary, $roundTripped->summary);
  }

  #[Test]
  public function testToDomainRefusesARecordWithoutAnEquipment(): void
  {
    $record = new EquipmentMaintenanceLogRecord();
    $record->id = self::LOG_ID;
    $record->equipment = null;

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Maintenance log record must reference an equipment.');

    MaintenanceLogMapper::toDomain($record);
  }
}
