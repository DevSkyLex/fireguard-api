<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationSystemRoleImmutableException.
 *
 * Raised when a caller tries to modify or delete a system role. System roles
 * are seeded and their permission sets are merged at read time
 * (`OrganizationSystemRoleCatalog`), so editing one would silently diverge from
 * the catalog rather than change anything durable.
 *
 * Mapped to **409**, arbitrated 2026-08-26: the role exists and the caller is
 * entitled, its system nature is what forbids the change.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSystemRoleImmutableException extends RuntimeException
{
  // #region Methods
  /**
   * Method cannotBeDeleted.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotBeDeleted(): self
  {
    return new self('System roles cannot be deleted.');
  }

  /**
   * Method cannotBeModified.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotBeModified(): self
  {
    return new self('System roles cannot be modified.');
  }
  // #endregion
}
