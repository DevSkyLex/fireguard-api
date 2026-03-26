<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationFacilityStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{FacilityStatisticsPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

use function max;

/**
 * UseCase GetOrganizationFacilityStatisticsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationFacilityStatisticsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationFacilityStatisticsHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param FacilityStatisticsPort $facilityStatistics the facility statistics port
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
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
   * @param GetOrganizationFacilityStatisticsQuery $query the query payload
   */
  public function __invoke(GetOrganizationFacilityStatisticsQuery $query): GetOrganizationFacilityStatisticsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.facilities.read')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.facilities.read');
    }

    $totalCount = $this->facilityStatistics->countFacilities($query->organizationId);
    $activeCount = $this->facilityStatistics->countActiveFacilities($query->organizationId);
    $countsByType = $this->facilityStatistics->countFacilitiesByType($query->organizationId);

    return new GetOrganizationFacilityStatisticsResult(
      totalCount: $totalCount,
      activeCount: $activeCount,
      archivedCount: max(0, $totalCount - $activeCount),
      countsByType: $countsByType,
    );
  }
  // #endregion
}
