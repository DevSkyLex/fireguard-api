<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Contract\Schedule;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceScheduleView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleView::class)]
final class MaintenanceScheduleViewTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $lastClosed = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $nextDue = new DateTimeImmutable('2026-04-01T00:00:00+00:00');
    $lastReminded = new DateTimeImmutable('2026-03-20T00:00:00+00:00');
    $created = new DateTimeImmutable('2025-12-01T00:00:00+00:00');
    $updated = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    $view = new MaintenanceScheduleView(
      id: 'schedule-1',
      organizationId: 'org-1',
      equipmentId: 'equip-1',
      facilityId: 'facility-1',
      equipmentType: 'fire_extinguisher',
      intervalOverride: 'P30D',
      lastInspectionClosedAt: $lastClosed,
      nextDueAt: $nextDue,
      dueStatus: 'up_to_date',
      lastRemindedAt: $lastReminded,
      remindedFor: $nextDue,
      createdAt: $created,
      updatedAt: $updated,
    );

    self::assertSame('schedule-1', $view->id);
    self::assertSame('org-1', $view->organizationId);
    self::assertSame('equip-1', $view->equipmentId);
    self::assertSame('facility-1', $view->facilityId);
    self::assertSame('fire_extinguisher', $view->equipmentType);
    self::assertSame('P30D', $view->intervalOverride);
    self::assertSame($lastClosed, $view->lastInspectionClosedAt);
    self::assertSame($nextDue, $view->nextDueAt);
    self::assertSame('up_to_date', $view->dueStatus);
    self::assertSame($lastReminded, $view->lastRemindedAt);
    self::assertSame($nextDue, $view->remindedFor);
    self::assertSame($created, $view->createdAt);
    self::assertSame($updated, $view->updatedAt);
  }

  #[Test]
  public function testAllowsNullableFields(): void
  {
    $view = new MaintenanceScheduleView(
      id: 'schedule-2',
      organizationId: 'org-1',
      equipmentId: 'equip-2',
      facilityId: null,
      equipmentType: 'alarm',
      intervalOverride: null,
      lastInspectionClosedAt: null,
      nextDueAt: null,
      dueStatus: 'unscheduled',
      lastRemindedAt: null,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    self::assertNull($view->facilityId);
    self::assertNull($view->intervalOverride);
    self::assertNull($view->lastInspectionClosedAt);
    self::assertNull($view->nextDueAt);
    self::assertNull($view->lastRemindedAt);
    self::assertNull($view->remindedFor);
    self::assertSame('unscheduled', $view->dueStatus);
  }
}
