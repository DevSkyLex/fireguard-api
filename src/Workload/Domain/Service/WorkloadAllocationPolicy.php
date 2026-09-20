<?php

declare(strict_types=1);

namespace Workload\Domain\Service;

use Workload\Domain\Model\Capacity\CapacitySchedule;
use Workload\Domain\ValueObject\{LocalDate, TaskAllocation, WorkDemand};

use function array_sum;
use function arsort;
use function intdiv;
use function max;

use const SORT_NUMERIC;

/**
 * Service WorkloadAllocationPolicy.
 *
 * Uses largest remainders with chronological ties to preserve integral minutes.
 * Allocation uses the complete task period, not the visible week. Existing load
 * is deliberately not an input: subtracting it would hide oversubscription.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadAllocationPolicy
{
  // #region Methods
  /**
   * Distributes integral remaining minutes proportionally across the full available period without capping overload.
   *
   * @since 1.0.0
   *
   * @param WorkDemand $demand remaining task effort and its effective work period
   * @param CapacitySchedule $schedule effective weekly capacity and dated individual exceptions
   * @param LocalDate $today current calendar date in the organization timezone
   *
   * @return TaskAllocation integral daily allocations or an explicit unallocated-work reason
   */
  public function allocate(WorkDemand $demand, CapacitySchedule $schedule, LocalDate $today): TaskAllocation
  {
    if ('none' === $demand->commitment || 0 === $demand->remainingMinutes) {
      return new TaskAllocation();
    }
    if (null === $demand->memberId) {
      return new TaskAllocation(unallocatedReason: 'unassigned');
    }
    if (null === $demand->remainingMinutes) {
      return new TaskAllocation(unallocatedReason: 'unestimated');
    }
    if (null === $demand->startsOn || null === $demand->endsOn) {
      return new TaskAllocation(unallocatedReason: 'undated');
    }
    if ($demand->endsOn->value < $today->value) {
      return new TaskAllocation(unallocatedReason: 'overdue');
    }
    $capacities = [];
    $start = LocalDate::fromString(max($today->value, $demand->startsOn->value));
    for ($day = $start; $day->value <= $demand->endsOn->value; $day = $day->next()) {
      $capacity = $schedule->on($day);
      if (null === $capacity) {
        return new TaskAllocation(unallocatedReason: 'unknown_capacity');
      }
      if ($capacity > 0) {
        $capacities[$day->value] = $capacity;
      }
    }
    $total = array_sum($capacities);
    if (0 === $total) {
      return new TaskAllocation(unallocatedReason: 'no_available_day');
    }
    $allocated = [];
    $remainders = [];
    foreach ($capacities as $date => $capacity) {
      $weighted = $demand->remainingMinutes * $capacity;
      $allocated[$date] = intdiv($weighted, $total);
      $remainders[$date] = $weighted % $total;
    }
    $leftover = $demand->remainingMinutes - array_sum($allocated);
    arsort($remainders, SORT_NUMERIC);
    foreach ($remainders as $date => $remainder) {
      if (0 === $leftover) {
        break;
      }
      ++$allocated[$date];
      --$leftover;
    }

    return new TaskAllocation($allocated);
  }
  // #endregion
}
