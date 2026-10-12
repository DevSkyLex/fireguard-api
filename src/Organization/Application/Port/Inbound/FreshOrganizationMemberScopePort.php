<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Interface FreshOrganizationMemberScopePort
 *
 * Publishes uncached membership scope for owner replay checks without exposing Organization persistence.
 *
 * @category Port
 */
interface FreshOrganizationMemberScopePort
{
  // #region Methods
  /**
   * @param string $memberId current target membership identity
   *
   * @return string|null current owning organization, including retained inactive memberships
   */
  public function organizationId(string $memberId): ?string;

  /**
   * @param string $memberId current target membership identity
   * @param string $organizationId original organization scope
   *
   * @return bool whether the target is currently active in this exact scope
   */
  public function isActive(string $memberId, string $organizationId): bool;
  // #endregion
}
