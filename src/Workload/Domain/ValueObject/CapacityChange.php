<?php

declare(strict_types=1);

namespace Workload\Domain\ValueObject;

/**
 * Value object CapacityChange.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityChange
{
  /**
   * @since 1.0.0
   *
   * @param LocalDate $effectiveOn first local date on which this capacity version applies
   * @param CapacityWeek $week effective-dated weekly capacity configuration
   */
  public function __construct(public LocalDate $effectiveOn, public CapacityWeek $week)
  {
  }
}
