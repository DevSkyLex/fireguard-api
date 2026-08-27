<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\AddTeamMember;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, TeamRepositoryPort};
use Organization\Domain\Event\Team\TeamMemberAddedEvent;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException, TeamNotFoundException};
use Organization\Domain\Exception\TeamMembershipException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, TeamId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function in_array;

/**
 * UseCase AddTeamMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddTeamMemberHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddTeamMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param TeamRepositoryPort $teamRepository the team repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private TeamRepositoryPort $teamRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
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
   * @param AddTeamMemberCommand $command the command payload
   */
  public function __invoke(AddTeamMemberCommand $command): AddTeamMemberResult
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

    $memberId = OrganizationMemberId::fromString($command->memberId);
    $member = $this->memberRepository->findById($memberId);

    if (null === $member || (string) $member->organizationId() !== (string) $organizationId) {
      throw OrganizationMemberNotFoundException::withId($command->memberId);
    }

    if (!$member->isActive()) {
      throw TeamMembershipException::memberNotActive();
    }

    if (in_array($command->memberId, $this->teamRepository->findMemberIds($teamId), true)) {
      throw TeamMembershipException::alreadyOnTheTeam();
    }

    $this->teamRepository->addMember($teamId, $memberId, $command->role);

    $this->eventDispatcher->dispatch(new TeamMemberAddedEvent(
      organizationId: $command->organizationId,
      teamId: $command->teamId,
      memberId: $command->memberId,
      role: $command->role,
    ));

    return new AddTeamMemberResult(
      teamId: (string) $teamId,
      memberId: (string) $memberId,
      role: $command->role,
      addedAt: new DateTimeImmutable(),
    );
  }
  // #endregion
}
