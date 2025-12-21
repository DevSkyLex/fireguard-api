<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject DateRange.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DateRange
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DateRange class.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $start the start date
   * @param DateTimeImmutable $end   the end date
   *
   * @throws InvalidValueException if the start date is after the end date
   */
  public function __construct(
    public DateTimeImmutable $start,
    public DateTimeImmutable $end,
  ) {
    if ($start > $end) {
      throw InvalidValueException::because(
        message: 'Start date must be before or equal to end date.'
      );
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method contains.
   *
   * Checks if a given date is within the range.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $date the date to check
   *
   * @return bool true if the date is within the range, false otherwise
   */
  public function contains(DateTimeImmutable $date): bool
  {
    return $date >= $this->start && $date <= $this->end;
  }

  /**
   * Method overlaps.
   *
   * Checks if this range overlaps with another range.
   *
   * @since 1.0.0
   *
   * @param self $other the other date range
   *
   * @return bool true if the ranges overlap, false otherwise
   */
  public function overlaps(self $other): bool
  {
    return $this->start <= $other->end && $this->end >= $other->start;
  }

  /**
   * Method duration.
   *
   * Returns the duration of the range in seconds.
   *
   * @since 1.0.0
   *
   * @return int the duration in seconds
   */
  public function duration(): int
  {
    return $this->end->getTimestamp() - $this->start->getTimestamp();
  }

  /**
   * Method isActive.
   *
   * Checks if the range is currently active (contains now).
   *
   * @since 1.0.0
   *
   * @return bool true if the range is active, false otherwise
   */
  public function isActive(): bool
  {
    return $this->contains(date: new DateTimeImmutable());
  }

  /**
   * Method equals.
   *
   * Compares two DateRange objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other DateRange object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->start == $other->start && $this->end == $other->end;
  }
  // #endregion
}
