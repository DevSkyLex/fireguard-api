<?php

declare(strict_types=1);

namespace Workload\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function array_is_list;
use function count;
use function is_int;

/**
 * Value object CapacityWeek.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityWeek
{
  /**
   * @var list<int>
   */
  public array $minutes;

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $minutes Monday through Sunday, validated at the boundary
   */
  public function __construct(array $minutes)
  {
    if (!array_is_list($minutes) || 7 !== count($minutes)) {
      throw InvalidValueException::because('A capacity week requires seven daily minute values.');
    }
    $validated = [];
    foreach ($minutes as $value) {
      if (!is_int($value) || $value < 0 || $value > 1440) {
        throw InvalidValueException::because('Daily capacity must be an integer between 0 and 1440 minutes.');
      }
      $validated[] = $value;
    }
    $this->minutes = $validated;
  }

  /**
   * Reads the configured capacity for the date's ISO weekday.
   *
   * @since 1.0.0
   *
   * @param LocalDate $date organization-local calendar date
   *
   * @return int configured daily capacity in minutes
   */
  public function on(LocalDate $date): int
  {
    return $this->minutes[$date->weekday() - 1];
  }
  // #endregion
}
