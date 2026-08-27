<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};

use function in_array;
use function sprintf;

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
   * @param string $action the workflow action named in the error message
   */
  public function assertResponsible(string $organizationId, string $userId, ?string $responsibleId, string $action = 'submit'): void
  {
    if (null === $responsibleId) {
      throw new InterventionConflictException(sprintf('A responsible member is required to %s the intervention.', $action));
    }

    $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);
    if (null === $member || !$member->isActive() || $member->id()->value !== $responsibleId) {
      throw new InterventionConflictException(sprintf('Only the responsible member can %s the intervention.', $action));
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
   * Method assertActiveMemberForUser.
   *
   * Resolves the authenticated user's membership in the organization and
   * asserts it is active, returning the resolved organization member id. Used
   * to attribute an authenticated action (e.g. a comment) to a member id
   * rather than the raw user id, so activity actors always resolve to a
   * member IRI at read time.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the user id value
   *
   * @return string the resolved organization member id
   */
  public function assertActiveMemberForUser(string $organizationId, string $userId): string
  {
    $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);
    if (null === $member) {
      throw new InterventionConflictException('The current user is not a member of the intervention organization.');
    }
    $this->assertActiveMember($organizationId, $member->id()->value);

    return $member->id()->value;
  }

  /**
   * Method findMemberId.
   *
   * Resolves the authenticated user's organization member id without
   * throwing, for best-effort actor attribution on system-recorded activities
   * (e.g. intervention creation, status transitions). Returns null when the
   * user cannot be resolved to an active member, so a missing membership
   * never blocks the underlying mutation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the user id value
   *
   * @return ?string the resolved organization member id, or null
   */
  public function findMemberId(string $organizationId, string $userId): ?string
  {
    $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($organizationId), $userId);

    return null !== $member && $member->isActive() ? $member->id()->value : null;
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
