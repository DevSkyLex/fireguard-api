<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride\SetMaintenanceScheduleOverrideResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test SetMaintenanceScheduleOverrideResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetMaintenanceScheduleOverrideResult::class)]
final class SetMaintenanceScheduleOverrideResultTest extends TestCase
{
  #[Test]
  public function testExposesTheUpdatedSchedule(): void
  {
    $view = new MaintenanceScheduleView(
      id: 'schedule-1',
      organizationId: 'org-1',
      equipmentId: 'equip-1',
      facilityId: null,
      equipmentType: 'alarm',
      intervalOverride: 'P30D',
      lastInspectionClosedAt: null,
      nextDueAt: null,
      dueStatus: 'unscheduled',
      lastRemindedAt: null,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $result = new SetMaintenanceScheduleOverrideResult($view);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($view, $result->schedule);
  }
}
