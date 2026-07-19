<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\CreateTeam;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Domain\Event\Team\TeamCreatedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNameAlreadyExistsException};
use Organization\Domain\Model\Team\Team;
use Organization\Domain\ValueObject\{OrganizationId, TeamId, TeamName};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase CreateTeamHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTeamHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateTeamHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param TeamRepositoryPort $teamRepository the team repository
   * @param UuidFactory $uuidFactory the UUID factory
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private TeamRepositoryPort $teamRepository,
    private UuidFactory $uuidFactory,
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
   * @param CreateTeamCommand $command the command payload
   */
  public function __invoke(CreateTeamCommand $command): CreateTeamResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $name = new TeamName($command->name);
    $existing = $this->teamRepository->findByOrganizationAndName($organizationId, $name);
    if (null !== $existing) {
      throw TeamNameAlreadyExistsException::withName((string) $name);
    }

    /** @var TeamId $teamId */
    $teamId = $this->uuidFactory->create(TeamId::class);

    $team = Team::create(
      id: $teamId,
      organizationId: $organizationId,
      name: $name,
      description: $command->description ?? '',
    );

    $this->teamRepository->save($team);

    $this->eventDispatcher->dispatch(new TeamCreatedEvent(
      organizationId: $command->organizationId,
      teamId: (string) $team->id(),
      name: (string) $team->name(),
    ));

    return new CreateTeamResult(
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
