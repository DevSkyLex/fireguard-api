<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationMembershipStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationStatus};
use Shared\Application\Message\QueryHandler;

use function count;
use function max;

/**
 * UseCase GetOrganizationMembershipStatisticsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationMembershipStatisticsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationMembershipStatisticsHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the member repository
   * @param OrganizationRoleRepositoryPort $roleRepository the role repository
   * @param OrganizationInvitationRepositoryPort $invitationRepository the invitation repository
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationInvitationRepositoryPort $invitationRepository,
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
   * @param GetOrganizationMembershipStatisticsQuery $query the query payload
   */
  public function __invoke(GetOrganizationMembershipStatisticsQuery $query): GetOrganizationMembershipStatisticsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.members.read')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.members.read');
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.roles.read')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.roles.read');
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.members.manage')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.members.manage');
    }

    $members = $this->memberRepository->findByOrganizationId($organizationId);
    $roles = $this->roleRepository->findByOrganizationId($organizationId);
    $invitations = $this->invitationRepository->findByOrganizationId($organizationId);

    $activeMemberCount = 0;
    foreach ($members as $member) {
      if ($member->isActive()) {
        ++$activeMemberCount;
      }
    }

    $systemRoleCount = 0;
    foreach ($roles as $role) {
      if ($role->isSystem()) {
        ++$systemRoleCount;
      }
    }

    $pendingInvitationCount = 0;
    $acceptedInvitationCount = 0;
    $revokedInvitationCount = 0;
    $expiredInvitationCount = 0;
    foreach ($invitations as $invitation) {
      match ($invitation->status()) {
        OrganizationInvitationStatus::PENDING => ++$pendingInvitationCount,
        OrganizationInvitationStatus::ACCEPTED => ++$acceptedInvitationCount,
        OrganizationInvitationStatus::REVOKED => ++$revokedInvitationCount,
        OrganizationInvitationStatus::EXPIRED => ++$expiredInvitationCount,
      };
    }

    $memberCount = count($members);
    $roleCount = count($roles);

    return new GetOrganizationMembershipStatisticsResult(
      memberCount: $memberCount,
      activeMemberCount: $activeMemberCount,
      inactiveMemberCount: max(0, $memberCount - $activeMemberCount),
      roleCount: $roleCount,
      systemRoleCount: $systemRoleCount,
      customRoleCount: max(0, $roleCount - $systemRoleCount),
      invitationCount: count($invitations),
      pendingInvitationCount: $pendingInvitationCount,
      acceptedInvitationCount: $acceptedInvitationCount,
      revokedInvitationCount: $revokedInvitationCount,
      expiredInvitationCount: $expiredInvitationCount,
    );
  }
  // #endregion
}
