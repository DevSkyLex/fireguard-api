<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{FacilityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationStatisticsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationStatisticsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationStatisticsHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository
   * @param FacilityStatisticsPort $facilityStatistics the facility statistics port
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private FacilityStatisticsPort $facilityStatistics,
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
   * @param GetOrganizationStatisticsQuery $query the query payload
   */
  public function __invoke(GetOrganizationStatisticsQuery $query): GetOrganizationStatisticsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.read')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.read');
    }

    $memberCount = $this->memberRepository->countByOrganizationId($organizationId);
    $roleCount = $this->roleRepository->countByOrganizationId($organizationId);
    $pendingInvitationCount = $this->invitationRepository->countPendingByOrganizationId($organizationId);
    $facilityCount = $this->facilityStatistics->countFacilities($query->organizationId);
    $activeFacilityCount = $this->facilityStatistics->countActiveFacilities($query->organizationId);

    return new GetOrganizationStatisticsResult(
      memberCount: $memberCount,
      roleCount: $roleCount,
      facilityCount: $facilityCount,
      activeFacilityCount: $activeFacilityCount,
      pendingInvitationCount: $pendingInvitationCount,
    );
  }
  // #endregion
}
