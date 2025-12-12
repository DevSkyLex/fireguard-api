<?php

declare(strict_types=1);

namespace Authorization\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception PermissionNotFoundException
 * @final
 *
 * Thrown when a permission cannot be found.
 *
 * @category Exception
 * @package Authorization\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PermissionNotFoundException extends DomainException
{
  //#region Methods
  /**
   * Method withId
   * @static
   *
   * Creates exception for permission 
   * not found by ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $permissionId The permission ID.
   *
   * @return self The exception instance.
   */
  public static function withId(string $permissionId): self
  {
    return new self(
      message: sprintf(
        'Permission with ID "%s" not found.',
        $permissionId
      )
    );
  }

  /**
   * Method withName
   * @static
   *
   * Creates exception for permission 
   * not found by name.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $permissionName The permission name.
   *
   * @return self The exception instance.
   */
  public static function withName(string $permissionName): self
  {
    return new self(
      message: sprintf(
        'Permission with name "%s" not found.',
        $permissionName
      )
    );
  }
  //#endregion
}
