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
}
