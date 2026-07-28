<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Factory;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Presentation\Api\Factory\MaintenanceScheduleOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceScheduleOutputFactoryTest.
 *
 * A schedule for equipment that is not attached to a facility must expose a
 * null relation rather than the IRI prefix alone, and `remindedFor` is
 * deliberately internal — it never reaches the payload.
 *
 * @category Factory Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleOutputFactory::class)]
final class MaintenanceScheduleOutputFactoryTest extends TestCase
{
  // #region Constants
  private const string SCHEDULE_ID = '550e8400-e29b-41d4-a716-446655485001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655485002';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655485003';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655485004';
  // #endregion

  // #region Methods
  #[Test]
  public function testFromViewBuildsTheRelationIrisAndFormatsEveryDate(): void
  {
    $output = new MaintenanceScheduleOutputFactory()->fromView($this->view(
      facilityId: self::FACILITY_ID,
      intervalOverride: 'P6M',
      lastInspectionClosedAt: new DateTimeImmutable('2026-01-10T00:00:00+00:00'),
      nextDueAt: new DateTimeImmutable('2026-07-10T00:00:00+00:00'),
      lastRemindedAt: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
    ));

    self::assertSame(self::SCHEDULE_ID, $output->id);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $output->organization);
    self::assertSame('/api/equipment/' . self::EQUIPMENT_ID, $output->equipment);
    self::assertSame('/api/facilities/' . self::FACILITY_ID, $output->facility);
    self::assertSame('extinguisher', $output->equipmentType);
    self::assertSame('P6M', $output->intervalOverride);
    self::assertSame('2026-01-10T00:00:00+00:00', $output->lastInspectionClosedAt);
    self::assertSame('2026-07-10T00:00:00+00:00', $output->nextDueAt);
    self::assertSame('due_soon', $output->dueStatus);
    self::assertSame('2026-07-01T00:00:00+00:00', $output->lastRemindedAt);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
    self::assertSame('2026-01-02T00:00:00+00:00', $output->updatedAt);
  }

  #[Test]
  public function testFromViewLeavesTheFacilityNullInsteadOfEmittingABareIriPrefix(): void
  {
    $output = new MaintenanceScheduleOutputFactory()->fromView($this->view());

    self::assertNull($output->facility);
    self::assertNull($output->intervalOverride);
    self::assertNull($output->lastInspectionClosedAt);
    self::assertNull($output->nextDueAt);
    self::assertNull($output->lastRemindedAt);
  }

  private function view(
    ?string $facilityId = null,
    ?string $intervalOverride = null,
    ?DateTimeImmutable $lastInspectionClosedAt = null,
    ?DateTimeImmutable $nextDueAt = null,
    ?DateTimeImmutable $lastRemindedAt = null,
  ): MaintenanceScheduleView {
    return new MaintenanceScheduleView(
      id: self::SCHEDULE_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::EQUIPMENT_ID,
      facilityId: $facilityId,
      equipmentType: 'extinguisher',
      intervalOverride: $intervalOverride,
      lastInspectionClosedAt: $lastInspectionClosedAt,
      nextDueAt: $nextDueAt,
      dueStatus: 'due_soon',
      lastRemindedAt: $lastRemindedAt,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }
  // #endregion
}
