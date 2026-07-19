<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject TeamId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a TeamId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the team identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
