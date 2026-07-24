<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Contract\Schedule;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleView};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceSchedulePage.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceSchedulePage::class)]
final class MaintenanceSchedulePageTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $view = new MaintenanceScheduleView(
      id: 'schedule-1',
      organizationId: 'org-1',
      equipmentId: 'equip-1',
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

    $page = new MaintenanceSchedulePage([$view], 2, 30, 41);

    self::assertSame([$view], $page->items);
    self::assertSame(2, $page->page);
    self::assertSame(30, $page->itemsPerPage);
    self::assertSame(41, $page->total);
  }

  #[Test]
  public function testAllowsAnEmptyPage(): void
  {
    $page = new MaintenanceSchedulePage([], 1, 30, 0);

    self::assertSame([], $page->items);
    self::assertSame(0, $page->total);
  }
}
