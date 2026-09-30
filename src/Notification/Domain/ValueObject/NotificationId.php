<?php

declare(strict_types=1);

namespace Notification\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * Class NotificationId
 *
 * Identifies a notification using the shared UUID representation.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationId extends Uuid
{
  // #region Methods
  /**
   * Method fromString
   *
   * Creates a notification identifier from its UUID representation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value the UUID representation
   *
   * @return self the notification identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
