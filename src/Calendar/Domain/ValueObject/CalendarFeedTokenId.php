<?php

declare(strict_types=1);

namespace Calendar\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject CalendarFeedTokenId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarFeedTokenId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a CalendarFeedTokenId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the calendar feed token identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
