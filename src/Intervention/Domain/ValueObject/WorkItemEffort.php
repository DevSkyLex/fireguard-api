<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use Intervention\Domain\Exception\InterventionValidationException;

use function is_int;

/**
 * Value object WorkItemEffort.
 *
 * Null means unknown. Actual time is intentionally absent from this value:
 * recording work cannot silently recalculate remaining effort.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkItemEffort
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param ?int $estimatedMinutes reference estimate in whole minutes; null means unestimated
   * @param ?int $remainingMinutes explicit remaining effort in whole minutes; never derived from actual time
   */
  public function __construct(public ?int $estimatedMinutes, public ?int $remainingMinutes)
  {
    self::minutes($estimatedMinutes);
    self::minutes($remainingMinutes);
  }

  /**
   * Validates integral nonnegative effort while preserving an unknown value.
   *
   * @since 1.0.0
   *
   * @param mixed $value candidate value to validate without silently coercing it
   *
   * @return ?int validated whole minutes, or null when effort is unknown
   */
  public static function minutes(mixed $value): ?int
  {
    if (null !== $value && (!is_int($value) || $value < 0 || $value > 2147483647)) {
      throw new InterventionValidationException('Effort must be a nonnegative integer number of minutes or null.');
    }

    return $value;
  }

  /**
   * Initializes reference and remaining effort from the optional estimate.
   *
   * @since 1.0.0
   *
   * @param ?int $minutes explicit whole-minute effort, or null when unknown
   *
   * @return self effort initialized without inventing an estimate
   */
  public static function estimated(?int $minutes): self
  {
    return new self($minutes, $minutes);
  }

  /**
   * Replaces only the explicit remaining effort, preserving the reference estimate.
   *
   * @since 1.0.0
   *
   * @param ?int $minutes explicit whole-minute effort, or null when unknown
   *
   * @return self effort with the new remaining value and unchanged reference estimate
   */
  public function reestimate(?int $minutes): self
  {
    return new self($this->estimatedMinutes, $minutes);
  }
  // #endregion
}
