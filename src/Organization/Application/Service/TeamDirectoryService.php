<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Contract\Team\TeamMembershipSnapshot;
use Organization\Application\Port\Inbound\TeamDirectoryPort;
use Organization\Application\Port\Outbound\TeamRepositoryPort;
use Organization\Domain\ValueObject\TeamId;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Service TeamDirectoryService.
 *
 * Implements the {@see TeamDirectoryPort} inbound port. Resolution is
 * defensive: a foreign team, a missing team, or a malformed identifier all
 * resolve to "not found" rather than throwing, since consumers (Intervention
 * team assignment) treat this as a read-only lookup.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamDirectoryService implements TeamDirectoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the TeamDirectoryService class.
   *
   * @since 1.0.0
   *
   * @param TeamRepositoryPort $teamRepository the team repository port
   */
  public function __construct(
    private TeamRepositoryPort $teamRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function resolveTeam(string $organizationId, string $teamId): ?TeamMembershipSnapshot
  {
    try {
      $team = $this->teamRepository->findById(TeamId::fromString($teamId));
    } catch (InvalidValueException) {
      return null;
    }

    if (null === $team || (string) $team->organizationId() !== $organizationId) {
      return null;
    }

    return new TeamMembershipSnapshot(
      teamId: (string) $team->id(),
      name: (string) $team->name(),
      memberIds: $this->teamRepository->findActiveMemberIds($team->id()),
    );
  }

  public function listActiveMemberIds(string $organizationId, string $teamId): array
  {
    $snapshot = $this->resolveTeam($organizationId, $teamId);

    return null === $snapshot ? [] : $snapshot->memberIds;
  }
  // #endregion
}
