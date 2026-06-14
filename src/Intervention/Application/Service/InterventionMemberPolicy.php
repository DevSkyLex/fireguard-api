<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};

use function in_array;

/**
 * Service InterventionMemberPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionMemberPolicy
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionMemberPolicy class.
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
      throw new InterventionConflictException('Intervention assignees must be active members of the intervention organization.');
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
      throw new InterventionConflictException('A responsible member is required to submit the intervention.');
    }

    $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);
    if (null === $member || !$member->isActive() || $member->id()->value !== $responsibleId) {
      throw new InterventionConflictException('Only the responsible member can submit the intervention.');
    }
  }

  /**
   * Method assertCanExecuteWorkItem.
   *
   * Ensures field work is performed by the assigned member, or by an active
   * intervention participant when the work item is not assigned.
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
      throw new InterventionAccessDeniedException('Only the assigned member can execute this work item.');
    }
  }

  /**
   * Method assertCanExecuteIntervention.
   *
   * Ensures field resources are mutated only by the responsible member or a
   * intervention participant.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the user id value
   * @param ?string $responsibleId the responsible member id value
   * @param list<string> $participants the participant member ids
   */
  public function assertCanExecuteIntervention(
    string $organizationId,
    string $userId,
    ?string $responsibleId,
    array $participants,
  ): void {
    $this->assertCanExecuteWorkItem($organizationId, $userId, $responsibleId, $participants, null);
  }
}
