<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Contract\Schedule\{MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\Service\MaintenanceScheduleService;
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;

/**
 * Test MaintenanceScheduleServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleService::class)]
final class MaintenanceScheduleServiceTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testOnInspectionClosedRecomputesExistingScheduleAndRearmsReminder(): void
  {
    $existing = new MaintenanceScheduleView(
      id: 'schedule-1',
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      intervalOverride: null,
      lastInspectionClosedAt: new DateTimeImmutable('2025-10-01T00:00:00+00:00'),
      nextDueAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      dueStatus: MaintenanceDueStatus::UP_TO_DATE->value,
      lastRemindedAt: new DateTimeImmutable('2025-12-15T00:00:00+00:00'),
      remindedFor: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2025-10-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2025-10-01T00:00:00+00:00'),
    );

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::once())
      ->method('findByOrganizationAndEquipment')
      ->with(self::ORG_ID, self::EQUIP_ID)
      ->willReturn($existing);

    $closedAt = new DateTimeImmutable('2026-01-15T00:00:00+00:00');

    $schedules->expects(self::once())
      ->method('save')
      ->with(self::callback(function (MaintenanceScheduleSnapshot $snapshot) use ($closedAt): bool {
        self::assertSame('schedule-1', $snapshot->id);
        self::assertSame($closedAt, $snapshot->lastInspectionClosedAt);
        self::assertSame('2026-04-15T00:00:00+00:00', $snapshot->nextDueAt?->format('c'));
        self::assertNull($snapshot->remindedFor);

        return true;
      }))
      ->willReturn($existing);

    $directory = $this->createMock(MaintenanceEquipmentDirectoryPort::class);
    $directory->expects(self::never())->method('findEquipment');

    $compliancePolicy = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $compliancePolicy->method('compliancePolicy')->willReturn(new MaintenanceCompliancePolicy(
      ['fire_extinguisher' => 'P90D'],
      14,
    ));

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-15T00:00:00+00:00'));

    $service = new MaintenanceScheduleService(
      $schedules,
      $directory,
      $compliancePolicy,
      new MaintenanceScheduleRecomputePolicy(),
      $clock,
    );

    $service->onInspectionClosed(self::ORG_ID, self::EQUIP_ID, $closedAt);
  }

  #[Test]
  public function testOnInspectionClosedBootstrapsAScheduleFromTheDirectoryWhenNoneExists(): void
  {
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findByOrganizationAndEquipment')->willReturn(null);
    $schedules->expects(self::once())
      ->method('save')
      ->with(self::callback(function (MaintenanceScheduleSnapshot $snapshot): bool {
        self::assertNull($snapshot->id);
        self::assertSame(self::ORG_ID, $snapshot->organizationId);
        self::assertSame(self::EQUIP_ID, $snapshot->equipmentId);
        self::assertSame('facility-9', $snapshot->facilityId);
        self::assertSame('sprinkler', $snapshot->equipmentType);

        return true;
      }))
      ->willReturn(new MaintenanceScheduleView(
        id: 'schedule-new',
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        facilityId: 'facility-9',
        equipmentType: 'sprinkler',
        intervalOverride: null,
        lastInspectionClosedAt: new DateTimeImmutable('2026-01-15T00:00:00+00:00'),
        nextDueAt: new DateTimeImmutable('2026-04-15T00:00:00+00:00'),
        dueStatus: 'up_to_date',
        lastRemindedAt: null,
        remindedFor: null,
        createdAt: new DateTimeImmutable('2026-01-15T00:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-01-15T00:00:00+00:00'),
      ));

    $directory = $this->createMock(MaintenanceEquipmentDirectoryPort::class);
    $directory->expects(self::once())
      ->method('findEquipment')
      ->with(self::EQUIP_ID)
      ->willReturn(new TrackableEquipment(self::EQUIP_ID, self::ORG_ID, 'facility-9', 'sprinkler', 'operational'));

    $compliancePolicy = $this->createStub(MaintenanceCompliancePolicyPort::class);
    $compliancePolicy->method('compliancePolicy')->willReturn(new MaintenanceCompliancePolicy(['sprinkler' => 'P90D'], 14));

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-15T00:00:00+00:00'));

    $service = new MaintenanceScheduleService(
      $schedules,
      $directory,
      $compliancePolicy,
      new MaintenanceScheduleRecomputePolicy(),
      $clock,
    );

    $service->onInspectionClosed(self::ORG_ID, self::EQUIP_ID, new DateTimeImmutable('2026-01-15T00:00:00+00:00'));
  }

  #[Test]
  public function testOnInspectionClosedIsANoOpWhenEquipmentIsUnknown(): void
  {
    /** @var MaintenanceScheduleRepositoryPort&MockObject $schedules */
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('findByOrganizationAndEquipment')->willReturn(null);
    $schedules->expects(self::never())->method('save');

    $directory = $this->createStub(MaintenanceEquipmentDirectoryPort::class);
    $directory->method('findEquipment')->willReturn(null);

    $service = new MaintenanceScheduleService(
      $schedules,
      $directory,
      $this->createStub(MaintenanceCompliancePolicyPort::class),
      new MaintenanceScheduleRecomputePolicy(),
      $this->createStub(ClockPort::class),
    );

    $service->onInspectionClosed(self::ORG_ID, self::EQUIP_ID, new DateTimeImmutable());
  }
}
