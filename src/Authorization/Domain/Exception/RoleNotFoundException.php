<?php

declare(strict_types=1);

namespace Authorization\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception RoleNotFoundException
 * @final
 *
 * Thrown when a role cannot be found.
 *
 * @category Exception
 * @package Authorization\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RoleNotFoundException extends DomainException
{
  //#region Methods
  /**
   * Method withId
   * @static
   *
   * Creates exception for role not found by ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $roleId The role ID.
   *
   * @return self The exception.
   */
  public static function withId(string $roleId): self
  {
    return new self(
      message: sprintf(
        'Role with ID "%s" not found.',
        $roleId
      )
    );
  }

  /**
   * Method withName
   * @static
   *
   * Creates exception for role not found by name.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $roleName The role name.
   *
   * @return self The exception.
   */
  public static function withName(string $roleName): self
  {
    return new self(
      message: sprintf(
        'Role with name "%s" not found.',
        $roleName
      )
    );
  }
  //#endregion
}
