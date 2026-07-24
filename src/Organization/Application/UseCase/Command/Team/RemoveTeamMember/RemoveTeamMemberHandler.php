<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\RemoveTeamMember;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Domain\Event\Team\TeamMemberRemovedEvent;
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamMemberNotFoundException, TeamNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, TeamId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function in_array;

/**
 * UseCase RemoveTeamMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTeamMemberHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveTeamMemberHandler class.
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
   * @param RemoveTeamMemberCommand $command the command payload
   */
  public function __invoke(RemoveTeamMemberCommand $command): RemoveTeamMemberResult
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

    if (!in_array($command->memberId, $this->teamRepository->findMemberIds($teamId), true)) {
      throw TeamMemberNotFoundException::withId($command->memberId);
    }

    $memberId = OrganizationMemberId::fromString($command->memberId);
    $this->teamRepository->removeMember($teamId, $memberId);

    $this->eventDispatcher->dispatch(new TeamMemberRemovedEvent(
      organizationId: $command->organizationId,
      teamId: $command->teamId,
      memberId: $command->memberId,
    ));

    return new RemoveTeamMemberResult(
      teamId: (string) $teamId,
      memberId: (string) $memberId,
    );
  }
  // #endregion
}
