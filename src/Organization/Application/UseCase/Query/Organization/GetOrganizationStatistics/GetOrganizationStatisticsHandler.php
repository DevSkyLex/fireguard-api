<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics;

use Organization\Application\Port\Outbound\{FacilityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\OrganizationNotFoundException;
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
  public function __construct(
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

    $memberCount = $this->memberRepository->countByOrganizationId($organizationId);
    $roleCount = $this->roleRepository->countByOrganizationId($organizationId);
    $pendingInvitationCount = $this->invitationRepository->countPendingByOrganizationId($organizationId);
    $facilityCount = $this->facilityStatistics->countActiveFacilities($query->organizationId);

    return new GetOrganizationStatisticsResult(
      memberCount: $memberCount,
      roleCount: $roleCount,
      facilityCount: $facilityCount,
      pendingInvitationCount: $pendingInvitationCount,
    );
  }
  // #endregion
}
