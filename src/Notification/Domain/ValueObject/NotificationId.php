<?php

declare(strict_types=1);

namespace Notification\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject NotificationId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the notification identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
