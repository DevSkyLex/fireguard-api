<?php

declare(strict_types=1);

namespace Workload\Domain\Model\Capacity;

use Shared\Domain\Exception\InvalidValueException;
use Workload\Domain\ValueObject\{CapacityChange, CapacityException, CapacityWeek, LocalDate};

/**
 * Model CapacitySchedule.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacitySchedule
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param list<CapacityChange> $organizationWeeks
   * @param list<CapacityChange> $memberWeeks
   * @param list<CapacityException> $exceptions
   */
  public function __construct(
    public array $organizationWeeks = [],
    public array $memberWeeks = [],
    public array $exceptions = [],
  ) {
    foreach ($exceptions as $index => $exception) {
      foreach ($exceptions as $otherIndex => $other) {
        if ($otherIndex > $index && $exception->startsOn->value <= $other->endsOn->value && $other->startsOn->value <= $exception->endsOn->value) {
          throw InvalidValueException::because('Availability exceptions cannot overlap.');
        }
      }
    }
    foreach ([$organizationWeeks, $memberWeeks] as $changes) {
      $dates = [];
      foreach ($changes as $change) {
        if (isset($dates[$change->effectiveOn->value])) {
          throw InvalidValueException::because('A week already takes effect on this date.');
        }
        $dates[$change->effectiveOn->value] = true;
      }
    }
  }

  /**
   * Resolves member overrides and dated exceptions, preserving unknown capacity.
   *
   * @since 1.0.0
   *
   * @param LocalDate $date organization-local calendar date
   *
   * @return ?int Configured daily capacity in minutes. Null means no capacity has been configured.
   */
  public function on(LocalDate $date): ?int
  {
    $week = $this->weekOn($this->memberWeeks, $date) ?? $this->weekOn($this->organizationWeeks, $date);
    $capacity = $week?->on($date);
    foreach ($this->exceptions as $exception) {
      if ($exception->startsOn->value <= $date->value && $exception->endsOn->value >= $date->value) {
        if (null !== $capacity && $exception->minutes > $capacity) {
          throw InvalidValueException::because('An exception cannot increase the configured daily capacity.');
        }

        return $exception->minutes;
      }
    }

    return $capacity;
  }

  /**
   * Selects the most recent capacity week effective on the requested date.
   *
   * @since 1.0.0
   *
   * @param list<CapacityChange> $changes
   * @param LocalDate $date organization-local calendar date
   *
   * @return ?CapacityWeek latest effective week, or null when none is configured
   */
  private function weekOn(array $changes, LocalDate $date): ?CapacityWeek
  {
    $selected = null;
    foreach ($changes as $change) {
      if ($change->effectiveOn->value <= $date->value && (null === $selected || $change->effectiveOn->value > $selected->effectiveOn->value)) {
        $selected = $change;
      }
    }

    return $selected?->week;
  }
  // #endregion
}
