<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\GetTeam;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, TeamId};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetTeamHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTeamHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetTeamHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param TeamRepositoryPort $teamRepository the team repository
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private TeamRepositoryPort $teamRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Returns a single team by identifier, scoped to an organization.
   *
   * @since 1.0.0
   *
   * @param GetTeamQuery $query the query payload
   *
   * @return GetTeamResult the team read model
   */
  public function __invoke(GetTeamQuery $query): GetTeamResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $teamId = TeamId::fromString($query->teamId);
    $team = $this->teamRepository->findById($teamId);

    if (null === $team || (string) $team->organizationId() !== (string) $organizationId) {
      throw TeamNotFoundException::withId($query->teamId);
    }

    return $this->toResult($team);
  }

  /**
   * Method toResult.
   *
   * Builds a read model from a team aggregate.
   *
   * @since 1.0.0
   *
   * @param Team $team the team aggregate
   *
   * @return GetTeamResult the team read model
   */
  private function toResult(Team $team): GetTeamResult
  {
    return new GetTeamResult(
      id: (string) $team->id(),
      organizationId: (string) $team->organizationId(),
      name: (string) $team->name(),
      description: $team->description(),
      memberCount: $this->teamRepository->countMembers($team->id()),
      createdAt: $team->createdAt(),
      updatedAt: $team->updatedAt(),
    );
  }
  // #endregion
}
