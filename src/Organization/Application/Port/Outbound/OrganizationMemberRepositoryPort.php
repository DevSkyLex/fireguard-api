<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};

/**
 * Port OrganizationMemberRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationMemberRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an organization member aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationMember $member the member aggregate
   */
  public function save(OrganizationMember $member): void;

  /**
   * Method findById.
   *
   * Finds a member by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $id the member identifier
   *
   * @return ?OrganizationMember the member aggregate when found
   */
  public function findById(OrganizationMemberId $id): ?OrganizationMember;

  /**
   * Method findByOrganizationAndUser.
   *
   * Finds a member by organization identifier and user identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return ?OrganizationMember the member aggregate when found
   */
  public function findByOrganizationAndUser(OrganizationId $organizationId, string $userId): ?OrganizationMember;

  /**
   * Method findByOrganizationId.
   *
   * Lists members for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<OrganizationMember> the organization members
   */
  public function findByOrganizationId(OrganizationId $organizationId): array;

  /**
   * Method findByUserId.
   *
   * Lists memberships for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return list<OrganizationMember> the user memberships
   */
  public function findByUserId(string $userId): array;

  /**
   * Method remove.
   *
   * Removes a member aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationMember $member the member aggregate
   */
  public function remove(OrganizationMember $member): void;

  /**
   * Method assignRole.
   *
   * Assigns a role to a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   * @param OrganizationRoleId $roleId the role identifier
   */
  public function assignRole(OrganizationMemberId $memberId, OrganizationRoleId $roleId): void;

  /**
   * Method findRoleIdsForMember.
   *
   * Returns role identifiers assigned to a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   *
   * @return list<string> the assigned role identifiers
   */
  public function findRoleIdsForMember(OrganizationMemberId $memberId): array;

  /**
   * Method getPermissionNamesForUserInOrganization.
   *
   * Resolves effective permission names for a user in an organization.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<string> the effective permission names
   */
  public function getPermissionNamesForUserInOrganization(string $userId, OrganizationId $organizationId): array;
  // #endregion
}
