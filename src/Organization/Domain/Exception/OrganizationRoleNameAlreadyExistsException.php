<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationRoleNameAlreadyExistsException.
 *
 * Raised when a role name is already taken inside the organization, on
 * creation or on rename. Fills the gap next to the module's existing
 * `PlanKeyAlreadyExistsException` and `TeamNameAlreadyExistsException`, which
 * model the same uniqueness family.
 *
 * Mapped to 400 — what the `InvalidArgumentException` it replaces already
 * answered. Its two siblings answer 409, which is the inconsistency this class
 * makes visible rather than resolves: changing it is a contract decision.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationRoleNameAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method create.
   *
   * Creates an exception for a duplicate role name.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function create(): self
  {
    return new self('Role name already exists for this organization.');
  }
  // #endregion
}
