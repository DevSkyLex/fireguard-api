<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Authorization\OrganizationAccessDecision;

/**
 * Port OrganizationAuthorizationPort.
 *
 * @category Port
 *
 * @version 1.1.0
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
   * Answers a single boolean and therefore cannot tell a non-member apart
   * from an unentitled member. A caller that must return 404 rather than 403
   * for a record outside the caller's scope needs {@see self::resolveAccess()}
   * instead — this method's denial cannot carry that distinction.
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
   * Method resolveAccess.
   *
   * Resolves a permission check into a three-state decision, separating
   * "no active membership in this organization" (OUTSIDE_SCOPE) from
   * "member, but not entitled" (MISSING_PERMISSION).
   *
   * The membership lookup only runs when the permission is not granted, so
   * the authorized path costs exactly what {@see self::hasPermission()} costs.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $permission the permission name
   *
   * @return OrganizationAccessDecision the resolved decision
   */
  public function resolveAccess(string $userId, string $organizationId, string $permission): OrganizationAccessDecision;

  /**
   * Method isMemberOf.
   *
   * Tells whether the user holds an ACTIVE membership in the organization,
   * independently of any permission.
   *
   * This is the scope half of {@see self::resolveAccess()}, split out for the
   * callers that must gate on scope BEFORE they can even name the permission
   * to require — typically because the required permission is derived from
   * the record's own state, and deriving it can itself fail in a way
   * (a conflict, a precondition) that would tell an outsider about a record
   * they must not know exists.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return bool true when an active membership exists
   */
  public function isMemberOf(string $userId, string $organizationId): bool;

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
