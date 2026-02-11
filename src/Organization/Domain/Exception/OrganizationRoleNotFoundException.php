<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationRoleNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationRoleNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing role identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the role identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Organization role with ID "%s" not found.', $id));
  }

  /**
   * Method withName.
   *
   * Creates an exception for a missing role name.
   *
   * @since 1.0.0
   *
   * @param string $name the role name
   *
   * @return self the exception instance
   */
  public static function withName(string $name): self
  {
    return new self(sprintf('Organization role "%s" not found.', $name));
  }
  // #endregion
}
