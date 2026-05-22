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
   * Method assertGrantedPermissions.
   *
   * Resolves the user's effective permissions once and asserts that every
   * required permission is granted. Throws on the first missing permission,
   * preserving fail-closed behavior while avoiding repeated DB round-trips.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param list<string> $permissions the permission names to check
   *
   * @throws \Organization\Domain\Exception\OrganizationAccessDeniedException
   *                                                                          when any of the required permissions is not granted
   */
  public function assertGrantedPermissions(string $userId, string $organizationId, array $permissions): void;

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
