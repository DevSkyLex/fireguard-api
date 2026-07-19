<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Port OrganizationPermissionGrantGuardPort.
 *
 * Enforces the no-privilege-escalation invariant: an actor may only grant
 * permissions (directly on a role, or by assigning an existing role) that the
 * actor already holds. Each method throws
 * {@see \Organization\Domain\Exception\OrganizationAccessDeniedException} on the
 * first permission the actor does not hold. Callers invoke this in the
 * Presentation layer BEFORE dispatching the command, so the thrown exception is
 * not wrapped by the message bus and maps cleanly to HTTP 403.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationPermissionGrantGuardPort
{
  // #region Methods
  /**
   * Method assertCanGrantPermissions.
   *
   * Asserts that the actor holds every permission it is trying to grant.
   *
   * @since 1.0.0
   *
   * @param string $actorUserId the acting user identifier
   * @param string $organizationId the organization identifier
   * @param list<string> $permissions the permissions being granted
   *
   * @throws \Organization\Domain\Exception\OrganizationAccessDeniedException when the actor lacks a granted permission
   */
  public function assertCanGrantPermissions(string $actorUserId, string $organizationId, array $permissions): void;

  /**
   * Method assertCanAssignRoles.
   *
   * Asserts that the actor holds every permission carried by each role it is
   * trying to assign. Unknown or cross-organization role ids are ignored here
   * (the owning use case rejects them separately).
   *
   * @since 1.0.0
   *
   * @param string $actorUserId the acting user identifier
   * @param string $organizationId the organization identifier
   * @param list<string> $roleIds the role identifiers being assigned
   *
   * @throws \Organization\Domain\Exception\OrganizationAccessDeniedException when a role carries a permission the actor lacks
   */
  public function assertCanAssignRoles(string $actorUserId, string $organizationId, array $roleIds): void;
  // #endregion
}
