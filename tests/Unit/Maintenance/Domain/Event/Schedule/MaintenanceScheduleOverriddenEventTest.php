<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Event\Schedule;

use DateTimeImmutable;
use Maintenance\Domain\Event\Schedule\MaintenanceScheduleOverriddenEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceScheduleOverriddenEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleOverriddenEvent::class)]
final class MaintenanceScheduleOverriddenEventTest extends TestCase
{
  #[Test]
  public function testExposesItsPayload(): void
  {
    $before = new DateTimeImmutable();
    $event = new MaintenanceScheduleOverriddenEvent('org-1', 'schedule-1', 'equip-1', 'P30D', 'user-1');
    $after = new DateTimeImmutable();

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('schedule-1', $event->scheduleId);
    self::assertSame('equip-1', $event->equipmentId);
    self::assertSame('P30D', $event->intervalOverride);
    self::assertSame('user-1', $event->actorUserId);
    self::assertGreaterThanOrEqual($before, $event->occurredAt);
    self::assertLessThanOrEqual($after, $event->occurredAt);
  }

  #[Test]
  public function testAllowsAClearedOverrideAndDefaultActor(): void
  {
    $event = new MaintenanceScheduleOverriddenEvent('org-1', 'schedule-1', 'equip-1', null);

    self::assertNull($event->intervalOverride);
    self::assertNull($event->actorUserId);
  }
}
