<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationLastAdminException.
 *
 * Raised when an operation would leave an organization without any active
 * member able to administer it (self-lockout protection).
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationLastAdminException extends RuntimeException
{
  /**
   * Method cannotRemoveLastAdmin.
   *
   * Creates an exception when removing a member would remove the last
   * administrator of the organization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotRemoveLastAdmin(): self
  {
    return new self('Cannot remove the last administrator of the organization.');
  }

  /**
   * Method cannotUnassignLastAdminRole.
   *
   * Creates an exception when unassigning a role would strip the last
   * administrator of its administration capability.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotUnassignLastAdminRole(): self
  {
    return new self('Cannot remove the last role that administers the organization.');
  }
}
