<?php

declare(strict_types=1);

namespace Notification\Application\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception NotificationNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @since 1.0.0
   *
   * @param string $id the notification identifier
   *
   * @return self the exception
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Notification "%s" not found.', $id));
  }
  // #endregion
}
