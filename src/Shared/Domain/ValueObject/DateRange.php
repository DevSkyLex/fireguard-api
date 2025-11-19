<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject DateRange
 * @final
 *
 * Represents a range between two dates.
 * Useful for token validity periods, session durations, etc.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DateRange
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the DateRange class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DateTimeImmutable $start The start date.
   * @param DateTimeImmutable $end The end date.
   *
   * @throws InvalidValueException If the start date is after the end date.
   */
  public function __construct(
    public DateTimeImmutable $start,
    public DateTimeImmutable $end
  ) {
    if ($start > $end) {
      throw InvalidValueException::because(
        message: 'Start date must be before or equal to end date.'
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method contains
   *
   * Checks if a given date is within the range.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DateTimeImmutable $date The date to check.
   *
   * @return bool True if the date is within the range, false otherwise.
   */
  public function contains(DateTimeImmutable $date): bool
  {
    return $date >= $this->start && $date <= $this->end;
  }

  /**
   * Method overlaps
   *
   * Checks if this range overlaps with another range.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other date range.
   *
   * @return bool True if the ranges overlap, false otherwise.
   */
  public function overlaps(self $other): bool
  {
    return $this->start <= $other->end && $this->end >= $other->start;
  }

  /**
   * Method duration
   *
   * Returns the duration of the range in seconds.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The duration in seconds.
   */
  public function duration(): int
  {
    return $this->end->getTimestamp() - $this->start->getTimestamp();
  }

  /**
   * Method isActive
   *
   * Checks if the range is currently active (contains now).
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if the range is active, false otherwise.
   */
  public function isActive(): bool
  {
    return $this->contains(date: new DateTimeImmutable());
  }

  /**
   * Method equals
   *
   * Compares two DateRange objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other DateRange object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->start == $other->start && $this->end == $other->end;
  }
  //#endregion
}
