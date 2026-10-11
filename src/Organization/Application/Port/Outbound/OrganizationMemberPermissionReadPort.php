<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port OrganizationMemberPermissionReadPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationMemberPermissionReadPort
{
  // #region Methods
  /**
   * Reads effective role grants for all active memberships in one organization batch.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization scope
   *
   * @return array<string, list<string>> permission names indexed by user identifier
   */
  public function activeUserPermissions(string $organizationId): array;
  // #endregion
}
