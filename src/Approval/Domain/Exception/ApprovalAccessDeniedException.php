<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ApprovalAccessDeniedException.
 *
 * Raised when the caller IS an active member of the organization but lacks
 * the permission the operation requires. A caller with no membership at all
 * gets {@see ApprovalRequestNotFoundException} instead — the 403/404
 * difference is itself an existence oracle across organizations.
 *
 * Module-owned on purpose, mirroring
 * {@see \Maintenance\Domain\Exception\MaintenanceAccessDeniedException}: the
 * decision and query handlers used to throw the Organization module's
 * `OrganizationAccessDeniedException`, which is a cross-module import of
 * another module's **Domain** — something ARCHITECTURE.md forbids and
 * `CrossModuleDomainBoundaryTest` ratchets down.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalAccessDeniedException extends RuntimeException
{
  // #region Methods
  /**
   * Method missingPermission.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $permission the permission name the caller lacks
   *
   * @return self the exception instance
   */
  public static function missingPermission(string $permission): self
  {
    return new self(sprintf('Missing %s permission.', $permission));
  }
  // #endregion
}
