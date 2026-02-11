<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Port OrganizationAuthorizationPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationAuthorizationPort
{
  // #region Methods
  /**
   * Method hasPermission.
   *
   * Checks whether a user has a required permission for an organization.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $permission the permission name
   *
   * @return bool true when permission is granted, false otherwise
   */
  public function hasPermission(string $userId, string $organizationId, string $permission): bool;

  /**
   * Method getUserPermissions.
   *
   * Returns every effective permission for a user in an organization.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return list<string> the permission names
   */
  public function getUserPermissions(string $userId, string $organizationId): array;
  // #endregion
}
