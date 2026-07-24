<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\DeleteTeam;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Domain\Event\Team\TeamDeletedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, TeamId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase DeleteTeamHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteTeamHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteTeamHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param TeamRepositoryPort $teamRepository the team repository
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private TeamRepositoryPort $teamRepository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Deletes a team from an organization. All team member assignments are
   * removed via cascade.
   *
   * @since 1.0.0
   *
   * @param DeleteTeamCommand $command the command payload
   *
   * @return DeleteTeamResult the use case result
   */
  public function __invoke(DeleteTeamCommand $command): DeleteTeamResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $teamId = TeamId::fromString($command->teamId);
    $team = $this->teamRepository->findById($teamId);

    if (null === $team || (string) $team->organizationId() !== (string) $organizationId) {
      throw TeamNotFoundException::withId($command->teamId);
    }

    $this->teamRepository->remove($team);

    $this->eventDispatcher->dispatch(new TeamDeletedEvent(
      organizationId: $command->organizationId,
      teamId: $command->teamId,
    ));

    return new DeleteTeamResult(
      teamId: (string) $teamId,
      organizationId: (string) $organizationId,
    );
  }
  // #endregion
}
