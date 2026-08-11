<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use DateTimeImmutable;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

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
   * Lists members for an organization, filtered and sorted at SQL level.
   * Every filter parameter is optional and defaults to "no filter", so
   * existing unfiltered callers keep receiving every member of the
   * organization ordered by `joinedAt` ascending. `$limit` of `null` returns
   * the full (unpaginated) result set.
   *
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?string $search free-text filter matched against the member's
   *                        user identifier (the only free-text column this
   *                        table owns; display name and email live in the
   *                        User module's database and cannot be joined here)
   * @param ?bool $isActive filters by membership activation state when set
   * @param ?OrganizationRoleId $roleId filters members holding this role
   * @param Sorting $sorting the sort field (`joinedAt` or `displayName`,
   *                         the latter falling back to `userId` for the
   *                         same reason as `$search`) and direction
   * @param ?int $limit the maximum number of members to return, or null for no pagination
   * @param ?int $offset the number of members to skip, ignored when `$limit` is null
   *
   * @return list<OrganizationMember> the organization members
   */
  public function findByOrganizationId(
    OrganizationId $organizationId,
    ?string $search = null,
    ?bool $isActive = null,
    ?OrganizationRoleId $roleId = null,
    Sorting $sorting = new Sorting('joinedAt', SortDirection::ASC),
    ?int $limit = null,
    ?int $offset = null,
  ): array;

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
   * Method unassignRole.
   *
   * Removes a role assignment from a member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the member identifier
   * @param OrganizationRoleId $roleId the role identifier to unassign
   */
  public function unassignRole(OrganizationMemberId $memberId, OrganizationRoleId $roleId): void;

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
   * Method countByOrganizationId.
   *
   * Counts members belonging to an organization, honoring the same optional
   * filters as {@see self::findByOrganizationId()} so a caller can compute a
   * filtered listing's total.
   *
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?string $search free-text filter matched against the member's user identifier
   * @param ?bool $isActive filters by membership activation state when set
   * @param ?OrganizationRoleId $roleId filters members holding this role
   *
   * @return int the member count
   */
  public function countByOrganizationId(
    OrganizationId $organizationId,
    ?string $search = null,
    ?bool $isActive = null,
    ?OrganizationRoleId $roleId = null,
  ): int;

  /**
   * Counts members for multiple organizations in one query.
   *
   * @since 1.0.0
   *
   * @param list<OrganizationId> $organizationIds the organization identifiers
   *
   * @return array<string, int> member counts indexed by organization ID
   */
  public function countByOrganizationIds(array $organizationIds): array;

  /**
   * Counts active members belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the active member count
   */
  public function countActiveByOrganizationId(OrganizationId $organizationId): int;

  /**
   * Counts members who joined during the requested period.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param DateTimeImmutable $joinedAtFrom the inclusive lower joined-at bound
   * @param DateTimeImmutable $joinedAtTo the inclusive upper joined-at bound
   *
   * @return int the joined member count for the period
   */
  public function countJoinedBetween(OrganizationId $organizationId, DateTimeImmutable $joinedAtFrom, DateTimeImmutable $joinedAtTo): int;

  /**
   * Method countJoinedByDay.
   *
   * Returns member join counts grouped by day for a period, honoring the
   * UTC-storage timestamp-without-timezone convention used by
   * `joined_at`. Used to build the dashboard members sparkline.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param DateTimeImmutable $from the inclusive lower joined-at bound
   * @param DateTimeImmutable $to the inclusive upper joined-at bound
   * @param ?string $timeZone optional IANA timezone used to bucket days; defaults to the lower bound's timezone
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countJoinedByDay(OrganizationId $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, ?string $timeZone = null): array;

  /**
   * Method findUserIdsByMemberIds.
   *
   * Resolves user identifiers for a bounded set of organization member
   * identifiers. Used to enrich cross-module summaries (e.g. the
   * dashboard recent-interventions list) with the responsible member's
   * user identity without exposing the member aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param list<string> $memberIds the member identifiers to resolve
   *
   * @return array<string, string> map of memberId => userId
   */
  public function findUserIdsByMemberIds(OrganizationId $organizationId, array $memberIds): array;

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

  /**
   * Method countActiveMembersGroupedByRoleId.
   *
   * Counts ACTIVE members assigned to each role of an organization in a
   * single grouped query (`GROUP BY role_id` over
   * `organization_member_roles`, joined to `organization_members` and
   * filtered on `is_active = true`), avoiding one COUNT query per role.
   * Roles with zero active assignments are simply absent from the map.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return array<string, int> map of roleId => active member count
   */
  public function countActiveMembersGroupedByRoleId(OrganizationId $organizationId): array;
  // #endregion
}
