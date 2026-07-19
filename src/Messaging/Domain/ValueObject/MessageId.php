<?php

declare(strict_types=1);

namespace Messaging\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject MessageId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessageId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the message identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
