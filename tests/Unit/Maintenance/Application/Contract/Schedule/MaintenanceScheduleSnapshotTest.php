<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Contract\Schedule;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceScheduleSnapshot.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleSnapshot::class)]
final class MaintenanceScheduleSnapshotTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $lastClosed = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $nextDue = new DateTimeImmutable('2026-04-01T00:00:00+00:00');
    $lastReminded = new DateTimeImmutable('2026-03-20T00:00:00+00:00');

    $snapshot = new MaintenanceScheduleSnapshot(
      id: 'schedule-1',
      organizationId: 'org-1',
      equipmentId: 'equip-1',
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      intervalOverride: 'P30D',
      lastInspectionClosedAt: $lastClosed,
      nextDueAt: $nextDue,
      dueStatus: 'due_soon',
      lastRemindedAt: $lastReminded,
      remindedFor: $nextDue,
    );

    self::assertSame('schedule-1', $snapshot->id);
    self::assertSame('org-1', $snapshot->organizationId);
    self::assertSame('equip-1', $snapshot->equipmentId);
    self::assertSame('facility-1', $snapshot->facilityId);
    self::assertSame('fire_extinguisher', $snapshot->equipmentType);
    self::assertSame('P30D', $snapshot->intervalOverride);
    self::assertSame($lastClosed, $snapshot->lastInspectionClosedAt);
    self::assertSame($nextDue, $snapshot->nextDueAt);
    self::assertSame('due_soon', $snapshot->dueStatus);
    self::assertSame($lastReminded, $snapshot->lastRemindedAt);
    self::assertSame($nextDue, $snapshot->remindedFor);
  }

  #[Test]
  public function testReminderFieldsDefaultToNullForACreateSnapshot(): void
  {
    $snapshot = new MaintenanceScheduleSnapshot(
      id: null,
      organizationId: 'org-1',
      equipmentId: 'equip-1',
      facilityId: null,
      equipmentType: 'alarm',
      intervalOverride: null,
      lastInspectionClosedAt: null,
      nextDueAt: null,
      dueStatus: 'unscheduled',
    );

    self::assertNull($snapshot->id);
    self::assertNull($snapshot->lastRemindedAt);
    self::assertNull($snapshot->remindedFor);
  }
}
