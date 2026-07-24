<?php

declare(strict_types=1);

namespace Calendar\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception CalendarEventNotFoundException.
 *
 * Also raised, mirroring {@see \Webhook\Domain\Exception\WebhookSubscriptionNotFoundException},
 * when an event exists but belongs to a different organization than the one
 * requested — information hiding, not a distinct access-denied case.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarEventNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the calendar event identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Calendar event with ID "%s" not found.', $id));
  }
  // #endregion
}
