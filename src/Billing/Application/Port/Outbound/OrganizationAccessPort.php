<?php

declare(strict_types=1);

namespace Billing\Application\Port\Outbound;

/**
 * Port OrganizationAccessPort.
 *
 * Outbound contract the Billing module uses to authorize a user against an
 * organization permission. The Organization module provides the adapter,
 * delegating to its authorization service.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationAccessPort
{
  // #region Methods
  /**
   * Method hasPermission.
   *
   * Returns whether the user holds the given permission in the organization.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $permission the permission name (e.g. organization.settings.write)
   *
   * @return bool true when the permission is granted
   */
  public function hasPermission(string $userId, string $organizationId, string $permission): bool;
  // #endregion
}
