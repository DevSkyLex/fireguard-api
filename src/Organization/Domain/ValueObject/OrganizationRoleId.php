<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject OrganizationRoleId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRoleId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates an OrganizationRoleId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the organization role identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
