<?php

declare(strict_types=1);

namespace Mission\Application\Service;

use Mission\Domain\Exception\{MissionAccessDeniedException, MissionConflictException};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};

use function in_array;

/**
 * Service MissionMemberPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionMemberPolicy
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionMemberPolicy class.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $members the members value
   */
  public function __construct(private OrganizationMemberRepositoryPort $members)
  {
  }

  /**
   * Method assertActiveMember.
   *
   * Executes the assert active member operation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $memberId the member id value
   */
  public function assertActiveMember(string $organizationId, string $memberId): void
  {
    $member = $this->members->findById(OrganizationMemberId::fromString($memberId));

    if (
      null === $member
      || !$member->isActive()
      || $member->organizationId()->value !== $organizationId
    ) {
      throw new MissionConflictException('Mission assignees must be active members of the mission organization.');
    }
  }

  /**
   * Method assertResponsible.
   *
   * Executes the assert responsible operation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the user id value
   * @param ?string $responsibleId the responsible id value
   */
  public function assertResponsible(string $organizationId, string $userId, ?string $responsibleId): void
  {
    if (null === $responsibleId) {
      throw new MissionConflictException('A responsible member is required to submit the mission.');
    }

    $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);
    if (null === $member || !$member->isActive() || $member->id()->value !== $responsibleId) {
      throw new MissionConflictException('Only the responsible member can submit the mission.');
    }
  }

  /**
   * Method assertCanExecuteWorkItem.
   *
   * Ensures field work is performed by the assigned member, or by an active
   * mission participant when the work item is not assigned.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the user id value
   * @param ?string $responsibleId the responsible member id value
   * @param list<string> $participants the participant member ids
   * @param ?string $assigneeId the work item assignee id value
   */
  public function assertCanExecuteWorkItem(
    string $organizationId,
    string $userId,
    ?string $responsibleId,
    array $participants,
    ?string $assigneeId,
  ): void {
    $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);
    $memberId = null !== $member && $member->isActive() ? $member->id()->value : null;
    $allowed = null !== $memberId && (
      (null !== $assigneeId && $memberId === $assigneeId)
      || (null === $assigneeId && ($memberId === $responsibleId || in_array($memberId, $participants, true)))
    );
    if (!$allowed) {
      throw new MissionAccessDeniedException('Only the assigned member can execute this work item.');
    }
  }

  /**
   * Method assertCanExecuteMission.
   *
   * Ensures field resources are mutated only by the responsible member or a
   * mission participant.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the user id value
   * @param ?string $responsibleId the responsible member id value
   * @param list<string> $participants the participant member ids
   */
  public function assertCanExecuteMission(
    string $organizationId,
    string $userId,
    ?string $responsibleId,
    array $participants,
  ): void {
    $this->assertCanExecuteWorkItem($organizationId, $userId, $responsibleId, $participants, null);
  }
}
