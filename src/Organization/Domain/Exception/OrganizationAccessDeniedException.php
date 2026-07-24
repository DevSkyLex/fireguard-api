<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationAccessDeniedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAccessDeniedException extends RuntimeException
{
  /**
   * Method missingPermission.
   *
   * Creates an exception for a missing organization permission.
   *
   * @since 1.0.0
   *
   * @param string $permission the required permission name
   *
   * @return self the exception instance
   */
  public static function missingPermission(string $permission): self
  {
    return new self(sprintf('Missing %s permission.', $permission));
  }

  /**
   * Method cannotGrantPermission.
   *
   * Creates an exception when a caller attempts to grant or assign a
   * permission they do not themselves hold (privilege-escalation guard).
   *
   * @since 1.0.0
   *
   * @param string $permission the permission the caller tried to grant
   *
   * @return self the exception instance
   */
  public static function cannotGrantPermission(string $permission): self
  {
    return new self(sprintf('Cannot grant permission "%s" that you do not hold.', $permission));
  }
}
