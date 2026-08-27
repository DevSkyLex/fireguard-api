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
 * Mapped to **409**, arbitrated 2026-08-26 — the same status its two siblings
 * already answered. It answered 400 only because it was a bare
 * `InvalidArgumentException`; the split with `PlanKeyAlreadyExistsException`
 * and `TeamNameAlreadyExistsException` is now closed.
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
