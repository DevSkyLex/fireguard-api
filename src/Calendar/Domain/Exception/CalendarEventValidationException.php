<?php

declare(strict_types=1);

namespace Calendar\Domain\Exception;

use RuntimeException;

/**
 * Exception CalendarEventValidationException.
 *
 * Raised for business-rule violations the aggregate itself enforces (an
 * `endsAt` before `startsAt`), never for shape-level input validation, which
 * the Symfony Validator constraints on the Input DTOs already cover.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarEventValidationException extends RuntimeException
{
  // #region Methods
  /**
   * Method endBeforeStart.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function endBeforeStart(): self
  {
    return new self('The event\'s end date/time cannot be before its start date/time.');
  }
  // #endregion
}
