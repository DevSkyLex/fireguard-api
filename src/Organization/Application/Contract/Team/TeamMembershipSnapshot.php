<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Team;

/**
 * Contract TeamMembershipSnapshot.
 *
 * Read model exposed outside the Organization module through
 * {@see \Organization\Application\Port\Inbound\TeamDirectoryPort} so
 * consumers (Intervention team assignment, the future Messaging
 * channel-membership binding) can resolve a team's active membership
 * without depending on the Organization module's Domain or Infrastructure
 * layers.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamMembershipSnapshot
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $teamId the team identifier
   * @param string $name the team display name
   * @param list<string> $memberIds the ACTIVE organization member identifiers assigned to the team
   */
  public function __construct(
    public string $teamId,
    public string $name,
    public array $memberIds,
  ) {
  }
  // #endregion
}
