<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\UpdateTeam;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Domain\Event\Team\TeamUpdatedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNameAlreadyExistsException, TeamNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, TeamId, TeamName};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase UpdateTeamHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateTeamHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateTeamHandler class.
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
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param UpdateTeamCommand $command the command payload
   */
  public function __invoke(UpdateTeamCommand $command): UpdateTeamResult
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

    if (null !== $command->name) {
      $name = new TeamName($command->name);
      if (!$name->equals($team->name())) {
        $existing = $this->teamRepository->findByOrganizationAndName($organizationId, $name);
        if (null !== $existing && (string) $existing->id() !== (string) $team->id()) {
          throw TeamNameAlreadyExistsException::withName((string) $name);
        }
      }
      $team->rename($name);
    }

    if (null !== $command->description) {
      $team->describe($command->description);
    }

    $this->teamRepository->save($team);

    $this->eventDispatcher->dispatch(new TeamUpdatedEvent(
      organizationId: $command->organizationId,
      teamId: (string) $team->id(),
      name: (string) $team->name(),
    ));

    return new UpdateTeamResult(
      id: (string) $team->id(),
      organizationId: (string) $team->organizationId(),
      name: (string) $team->name(),
      description: $team->description(),
      createdAt: $team->createdAt(),
      updatedAt: $team->updatedAt(),
    );
  }
  // #endregion
}
