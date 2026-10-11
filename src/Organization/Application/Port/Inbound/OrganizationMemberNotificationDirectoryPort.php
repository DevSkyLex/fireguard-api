<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Port OrganizationMemberNotificationDirectoryPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationMemberNotificationDirectoryPort
{
  // #region Methods
  /**
   * Resolves active notification recipients whose effective grants match a permission.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization scope
   * @param string $permission the administrator permission required by the sender
   *
   * @return list<string> the qualifying user identifiers
   */
  public function administratorUserIds(string $organizationId, string $permission): array;
  // #endregion
}
