<?php

declare(strict_types=1);

namespace Tests\Unit\Workload\Domain\Service;

use PHPUnit\Framework\TestCase;
use Workload\Domain\Model\Capacity\CapacitySchedule;
use Workload\Domain\Service\WorkloadAllocationPolicy;
use Workload\Domain\ValueObject\{CapacityChange, CapacityException, CapacityWeek, DailyWorkload, LocalDate, WorkDemand};

use function array_sum;

final class WorkloadAllocationPolicyTest extends TestCase
{
  public function testSixHoursPlusTwoExceedsSevenByOneHour(): void
  {
    $day = LocalDate::fromString('2026-09-14');
    $schedule = new CapacitySchedule([new CapacityChange($day, new CapacityWeek([420, 420, 420, 420, 420, 0, 0]))]);
    $policy = new WorkloadAllocationPolicy();
    $first = $policy->allocate(new WorkDemand('a', 'member', 360, $day, $day, 'committed'), $schedule, $day);
    $second = $policy->allocate(new WorkDemand('b', 'member', 120, $day, $day, 'committed'), $schedule, $day);
    self::assertSame(360, $first->dailyMinutes[$day->value]);
    self::assertSame(120, $second->dailyMinutes[$day->value]);
    $allocatedMinutes = $first->dailyMinutes[$day->value] + $second->dailyMinutes[$day->value];
    self::assertSame(60, new DailyWorkload(420, 0, $allocatedMinutes)->overloadMinutes());
  }

  public function testTodayCombinesActualAndRemainingWithoutDeduction(): void
  {
    $load = new DailyWorkload(420, 120, 180);
    self::assertSame(180, $load->remainingMinutes);
    self::assertEqualsWithDelta(100 * 300 / 420, $load->utilizationPercent(), 0.001);
  }

  public function testAllocationUsesEntirePeriodAndPreservesEveryMinute(): void
  {
    $monday = LocalDate::fromString('2026-09-14');
    $schedule = new CapacitySchedule([new CapacityChange($monday, new CapacityWeek([420, 210, 0, 0, 0, 0, 0]))]);
    $allocation = new WorkloadAllocationPolicy()->allocate(new WorkDemand('task', 'member', 100, $monday, LocalDate::fromString('2026-09-22'), 'committed'), $schedule, $monday);
    self::assertSame(['2026-09-14' => 33, '2026-09-15' => 17, '2026-09-21' => 33, '2026-09-22' => 17], $allocation->dailyMinutes);
    self::assertSame(100, array_sum($allocation->dailyMinutes));
  }

  public function testMissingInputsRemainVisible(): void
  {
    $today = LocalDate::fromString('2026-09-16');
    $policy = new WorkloadAllocationPolicy();
    $schedule = new CapacitySchedule();
    self::assertSame('unassigned', $policy->allocate(new WorkDemand('a', null, 60, null, null, 'committed'), $schedule, $today)->unallocatedReason);
    self::assertSame('unestimated', $policy->allocate(new WorkDemand('b', 'm', null, null, null, 'committed'), $schedule, $today)->unallocatedReason);
    self::assertSame('undated', $policy->allocate(new WorkDemand('c', 'm', 60, null, null, 'committed'), $schedule, $today)->unallocatedReason);
    self::assertSame('overdue', $policy->allocate(new WorkDemand('d', 'm', 60, $today, $today, 'committed'), $schedule, $today->next())->unallocatedReason);
    self::assertSame('unknown_capacity', $policy->allocate(new WorkDemand('e', 'm', 60, $today, $today, 'committed'), $schedule, $today)->unallocatedReason);
    self::assertSame('no_available_day', $policy->allocate(new WorkDemand('f', 'm', 60, $today, $today, 'committed'), new CapacitySchedule([], [], [new CapacityException($today, $today, 0)]), $today)->unallocatedReason);
  }

  public function testUnknownWorkCannotBeAdvertisedAsAvailable(): void
  {
    self::assertSame('unknown', new DailyWorkload(420, 0, 0, hasUnquantifiedWork: true)->availability());
    self::assertSame('partial', new DailyWorkload(420, 0, 0, hasUnquantifiedWork: true)->completeness());
    self::assertNull(new DailyWorkload(0, 60, 0)->utilizationPercent());
    self::assertSame('unavailable', new DailyWorkload(0, 60, 0)->availability());
    self::assertSame('unavailable', new DailyWorkload(null, 60, 0)->completeness());
  }

  public function testTerminalTasksDoNotContributeFutureDemand(): void
  {
    $allocation = new WorkloadAllocationPolicy()->allocate(new WorkDemand('done', 'member', 180, null, null, 'none'), new CapacitySchedule(), LocalDate::fromString('2026-09-16'));
    self::assertSame([], $allocation->dailyMinutes);
    self::assertNull($allocation->unallocatedReason);
  }
}
