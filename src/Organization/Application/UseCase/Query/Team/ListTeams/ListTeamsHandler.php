<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\ListTeams;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Application\UseCase\Query\Team\GetTeam\GetTeamResult;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListTeamsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTeamsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListTeamsHandler class.
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
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param ListTeamsQuery $query the query payload
   */
  public function __invoke(ListTeamsQuery $query): ListTeamsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $teams = $this->teamRepository->findByOrganizationId($organizationId);

    $results = [];
    foreach ($teams as $team) {
      $results[] = $this->toResult($team);
    }

    return new ListTeamsResult($results);
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
