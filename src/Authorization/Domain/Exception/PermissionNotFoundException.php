<?php

declare(strict_types=1);

namespace Authorization\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception PermissionNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PermissionNotFoundException extends DomainException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * Creates exception for permission
   * not found by ID.
   *
   * @since 1.0.0
   *
   * @param string $permissionId the permission ID
   *
   * @return self the exception instance
   */
  public static function withId(string $permissionId): self
  {
    return new self(
      message: sprintf(
        'Permission with ID "%s" not found.',
        $permissionId,
      ),
    );
  }

  /**
   * Method withName.
   *
   * @static
   *
   * Creates exception for permission
   * not found by name.
   *
   * @since 1.0.0
   *
   * @param string $permissionName the permission name
   *
   * @return self the exception instance
   */
  public static function withName(string $permissionName): self
  {
    return new self(
      message: sprintf(
        'Permission with name "%s" not found.',
        $permissionName,
      ),
    );
  }
  // #endregion
}
