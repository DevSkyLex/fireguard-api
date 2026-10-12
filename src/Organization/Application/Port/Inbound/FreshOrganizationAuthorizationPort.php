<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Interface FreshOrganizationAuthorizationPort
 *
 * Publishes uncached current membership and effective grants for pre-decode replay gates without exposing main Records or repositories.
 *
 * @category Port
 */
interface FreshOrganizationAuthorizationPort extends OrganizationAuthorizationPort
{
  // #region Methods
  /**
   * Method activeMemberId
   *
   * @access public
   *
   * @param string $userId authenticated account identity
   * @param string $organizationId exact organization context
   *
   * @return string|null current active membership identity, bypassing managed entity and permission caches
   */
  public function activeMemberId(string $userId, string $organizationId): ?string;

  /**
   * Method memberSatisfiesRole
   *
   * @param string $userId authenticated account identity
   * @param string $organizationId current organization context
   * @param string $minRole owner-defined minimum role tier
   *
   * @return bool whether the fresh active member satisfies the configured tier
   */
  public function memberSatisfiesRole(string $userId, string $organizationId, string $minRole): bool;
  // #endregion
}
