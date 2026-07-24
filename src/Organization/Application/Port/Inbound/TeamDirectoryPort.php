<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Team\TeamMembershipSnapshot;

/**
 * Port TeamDirectoryPort.
 *
 * Inbound port letting other modules resolve an organization team's active
 * membership without depending on the Organization module's Domain or
 * Infrastructure layers. Consumed directly cross-module, exactly like the
 * existing {@see OrganizationAuthorizationPort} (Intervention's team
 * assignment endpoint today; the future Messaging channel<->team
 * membership binding).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TeamDirectoryPort
{
  // #region Methods
  /**
   * Method resolveTeam.
   *
   * Resolves a team's membership snapshot, scoped to an organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $teamId the team identifier
   *
   * @return ?TeamMembershipSnapshot the team membership snapshot, or null when the team does not exist in this organization
   */
  public function resolveTeam(string $organizationId, string $teamId): ?TeamMembershipSnapshot;

  /**
   * Method listActiveMemberIds.
   *
   * Returns the ACTIVE organization member identifiers assigned to a team.
   * Returns an empty list when the team does not exist in this
   * organization or has no active members.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $teamId the team identifier
   *
   * @return list<string> the active member identifiers
   */
  public function listActiveMemberIds(string $organizationId, string $teamId): array;
  // #endregion
}
