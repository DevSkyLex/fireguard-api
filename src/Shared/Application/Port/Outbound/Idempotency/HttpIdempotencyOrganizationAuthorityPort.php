<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

/**
 * Interface HttpIdempotencyOrganizationAuthorityPort
 *
 * Rechecks current owner-scoped requirements without coupling the generic receipt driver to an organization module.
 *
 * @category Port
 */
interface HttpIdempotencyOrganizationAuthorityPort
{
  // #region Methods
  /**
   * @param string $principalId authenticated account identity
   * @param string $organizationId signed original organization scope
   */
  public function isMemberOf(string $principalId, string $organizationId): bool;

  /**
   * @param string $principalId authenticated account identity
   * @param string $organizationId signed original organization scope
   * @param string $permission registered required owner permission
   */
  public function hasPermission(string $principalId, string $organizationId, string $permission): bool;
  // #endregion
}
