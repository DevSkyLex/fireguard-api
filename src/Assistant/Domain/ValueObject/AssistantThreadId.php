<?php

declare(strict_types=1);

namespace Assistant\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject AssistantThreadId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantThreadId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * @static
   *
   * Creates an AssistantThreadId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the assistant thread identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
