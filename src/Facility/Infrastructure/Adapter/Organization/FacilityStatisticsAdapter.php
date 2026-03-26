<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Organization;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\{FacilityOrganizationId, FacilityType};
use Organization\Application\Port\Outbound\FacilityStatisticsPort;

/**
 * Adapter FacilityStatisticsAdapter.
 *
 * Implements the Organization module's facility statistics port
 * using the Facility module's repository.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityStatisticsAdapter implements FacilityStatisticsPort
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function countFacilities(string $organizationId): int
  {
    return $this->facilityRepository->countByOrganizationId(
      organizationId: FacilityOrganizationId::fromString($organizationId),
      includeArchived: true,
    );
  }

  /**
   * {@inheritDoc}
   */
  public function countActiveFacilities(string $organizationId): int
  {
    return $this->facilityRepository->countActiveByOrganizationId(
      FacilityOrganizationId::fromString($organizationId),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function countFacilitiesByType(string $organizationId): array
  {
    $organizationIdVo = FacilityOrganizationId::fromString($organizationId);
    $counts = [];

    foreach (FacilityType::cases() as $type) {
      $counts[$type->value] = $this->facilityRepository->countByOrganizationId(
        organizationId: $organizationIdVo,
        includeArchived: true,
        type: $type->value,
      );
    }

    return $counts;
  }
  // #endregion
}
