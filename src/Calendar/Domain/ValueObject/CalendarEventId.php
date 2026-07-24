<?php

declare(strict_types=1);

namespace Calendar\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject CalendarEventId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarEventId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a CalendarEventId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the calendar event identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
