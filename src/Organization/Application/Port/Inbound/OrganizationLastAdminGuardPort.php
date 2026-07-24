<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Port OrganizationLastAdminGuardPort.
 *
 * Protects an organization from being left without any active administrator
 * (a member holding organization.members.manage, directly or via a wildcard).
 * Each method loads the involved members/roles internally and throws
 * {@see \Organization\Domain\Exception\OrganizationLastAdminException} when the
 * operation would remove the last administrator.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationLastAdminGuardPort
{
  // #region Methods
  /**
   * Method assertCanRemoveMember.
   *
   * Asserts that deactivating a single member keeps at least one active
   * administrator in the organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the member identifier being removed
   *
   * @throws \Organization\Domain\Exception\OrganizationLastAdminException when the member is the last administrator
   */
  public function assertCanRemoveMember(string $organizationId, string $memberId): void;

  /**
   * Method assertCanUnassignRole.
   *
   * Asserts that unassigning a role from a member keeps at least one active
   * administrator in the organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the member identifier losing the role
   * @param string $roleId the role identifier being unassigned
   *
   * @throws \Organization\Domain\Exception\OrganizationLastAdminException when unassigning strips the last administrator
   */
  public function assertCanUnassignRole(string $organizationId, string $memberId, string $roleId): void;

  /**
   * Method assertCanRemoveMembers.
   *
   * Asserts that a batch removal does not deactivate every active
   * administrator of the organization at once.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param list<string> $memberIds the member identifiers scheduled for removal
   *
   * @throws \Organization\Domain\Exception\OrganizationLastAdminException when the batch removes every administrator
   */
  public function assertCanRemoveMembers(string $organizationId, array $memberIds): void;

  /**
   * Method assertCanUpdateRolePermissions.
   *
   * Asserts that changing a role's permissions does not remove the last
   * administrator (e.g. stripping organization.members.manage from the only
   * admin-granting role).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier being updated
   * @param list<string> $newPermissions the role's permissions after the update
   *
   * @throws \Organization\Domain\Exception\OrganizationLastAdminException when the change removes the last administrator
   */
  public function assertCanUpdateRolePermissions(string $organizationId, string $roleId, array $newPermissions): void;

  /**
   * Method assertCanDeleteRole.
   *
   * Asserts that deleting a role does not remove the last administrator.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier being deleted
   *
   * @throws \Organization\Domain\Exception\OrganizationLastAdminException when the deletion removes the last administrator
   */
  public function assertCanDeleteRole(string $organizationId, string $roleId): void;
  // #endregion
}
