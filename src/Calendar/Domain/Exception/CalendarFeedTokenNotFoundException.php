<?php

declare(strict_types=1);

namespace Calendar\Domain\Exception;

use RuntimeException;

/**
 * Exception CalendarFeedTokenNotFoundException.
 *
 * Raised when no active calendar feed token matches a lookup — whether the
 * token never existed, was revoked, or its member can no longer read the
 * organization feed. One single exception on purpose: the public `.ics`
 * endpoint must answer a uniform 404 with no oracle about which case it was.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenNotFoundException extends RuntimeException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CalendarFeedTokenNotFoundException class.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
    parent::__construct('Calendar feed token not found.');
  }
  // #endregion
}
