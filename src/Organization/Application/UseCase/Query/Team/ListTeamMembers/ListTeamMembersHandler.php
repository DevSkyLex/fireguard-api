<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\ListTeamMembers;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, TeamId};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListTeamMembersHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTeamMembersHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListTeamMembersHandler class.
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
   * @param ListTeamMembersQuery $query the query payload
   */
  public function __invoke(ListTeamMembersQuery $query): ListTeamMembersResult
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

    $memberships = [];
    foreach ($this->teamRepository->findMemberships($teamId) as $membership) {
      $memberships[] = new TeamMembershipResult(
        memberId: $membership['memberId'],
        role: $membership['role'],
        addedAt: $membership['addedAt'],
      );
    }

    return new ListTeamMembersResult($memberships);
  }
  // #endregion
}
