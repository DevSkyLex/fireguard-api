<?php

declare(strict_types=1);

namespace Workload\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

/**
 * Value object CapacityException: actual daily availability over an inclusive period.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityException
{
  /**
   * @since 1.0.0
   *
   * @param LocalDate $startsOn inclusive organization-local start date
   * @param LocalDate $endsOn inclusive organization-local end date
   * @param int $minutes duration in whole minutes
   */
  public function __construct(
    public LocalDate $startsOn,
    public LocalDate $endsOn,
    public int $minutes,
  ) {
    if ($startsOn->value > $endsOn->value || $minutes < 0 || $minutes > 1440) {
      throw InvalidValueException::because('Invalid availability exception.');
    }
  }
}
