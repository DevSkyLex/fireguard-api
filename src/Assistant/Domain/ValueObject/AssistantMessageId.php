<?php

declare(strict_types=1);

namespace Assistant\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject AssistantMessageId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantMessageId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * @static
   *
   * Creates an AssistantMessageId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the assistant message identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
