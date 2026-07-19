<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use DateTimeImmutable;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, TeamId, TeamName};

/**
 * Port TeamRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TeamRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a team aggregate.
   *
   * @since 1.0.0
   *
   * @param Team $team the team aggregate
   */
  public function save(Team $team): void;

  /**
   * Method remove.
   *
   * Deletes a team aggregate and cascades its memberships.
   *
   * @since 1.0.0
   *
   * @param Team $team the team aggregate to delete
   */
  public function remove(Team $team): void;

  /**
   * Method findById.
   *
   * Finds a team by identifier.
   *
   * @since 1.0.0
   *
   * @param TeamId $id the team identifier
   *
   * @return ?Team the team aggregate when found
   */
  public function findById(TeamId $id): ?Team;

  /**
   * Method findByOrganizationAndName.
   *
   * Finds a team by organization identifier and team name.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param TeamName $name the team name
   *
   * @return ?Team the team aggregate when found
   */
  public function findByOrganizationAndName(OrganizationId $organizationId, TeamName $name): ?Team;

  /**
   * Method findByOrganizationId.
   *
   * Lists teams belonging to an organization, ordered by name.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<Team> the teams list
   */
  public function findByOrganizationId(OrganizationId $organizationId): array;

  /**
   * Method addMember.
   *
   * Adds an organization member to a team. Idempotent: adding an already
   * assigned member is a no-op.
   *
   * @since 1.0.0
   *
   * @param TeamId $teamId the team identifier
   * @param OrganizationMemberId $memberId the organization member identifier
   * @param ?string $role the free-form membership label, when set
   */
  public function addMember(TeamId $teamId, OrganizationMemberId $memberId, ?string $role = null): void;

  /**
   * Method removeMember.
   *
   * Removes an organization member from a team.
   *
   * @since 1.0.0
   *
   * @param TeamId $teamId the team identifier
   * @param OrganizationMemberId $memberId the organization member identifier
   */
  public function removeMember(TeamId $teamId, OrganizationMemberId $memberId): void;

  /**
   * Method findMemberIds.
   *
   * Returns every member identifier assigned to a team, regardless of the
   * member's active status.
   *
   * @since 1.0.0
   *
   * @param TeamId $teamId the team identifier
   *
   * @return list<string> the assigned member identifiers
   */
  public function findMemberIds(TeamId $teamId): array;

  /**
   * Method findActiveMemberIds.
   *
   * Returns member identifiers assigned to a team, restricted to
   * organization members currently active.
   *
   * @since 1.0.0
   *
   * @param TeamId $teamId the team identifier
   *
   * @return list<string> the active assigned member identifiers
   */
  public function findActiveMemberIds(TeamId $teamId): array;

  /**
   * Method countMembers.
   *
   * Counts members assigned to a team, regardless of active status.
   *
   * @since 1.0.0
   *
   * @param TeamId $teamId the team identifier
   *
   * @return int the assigned member count
   */
  public function countMembers(TeamId $teamId): int;

  /**
   * Method findMemberships.
   *
   * Returns every membership row for a team (member identifier, free-form
   * role label, and membership timestamp), regardless of the member's
   * active status. Used to render the team members listing.
   *
   * @since 1.0.0
   *
   * @param TeamId $teamId the team identifier
   *
   * @return list<array{memberId: string, role: ?string, addedAt: DateTimeImmutable}> the membership rows
   */
  public function findMemberships(TeamId $teamId): array;

  /**
   * Method deleteMembershipsForMember.
   *
   * Deletes every team membership row for an organization member across
   * all of the organization's teams. Used when a member is removed from
   * the organization so team_members never carries a dangling membership.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberId $memberId the organization member identifier
   */
  public function deleteMembershipsForMember(OrganizationMemberId $memberId): void;
  // #endregion
}
