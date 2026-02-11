<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject OrganizationId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates an OrganizationId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the organization identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
