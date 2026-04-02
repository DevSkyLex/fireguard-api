<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Organization;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\{FacilityOrganizationId, FacilityStatus, FacilityType};
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
  public function countFacilities(string $organizationId, ?string $type = null): int
  {
    return $this->facilityRepository->countByOrganizationId(
      organizationId: FacilityOrganizationId::fromString($organizationId),
      includeArchived: true,
      type: $type,
    );
  }

  /**
   * {@inheritDoc}
   */
  public function countActiveFacilities(string $organizationId, ?string $type = null): int
  {
    return $this->facilityRepository->countByOrganizationId(
      organizationId: FacilityOrganizationId::fromString($organizationId),
      includeArchived: false,
      type: $type,
      status: FacilityStatus::ACTIVE->value,
    );
  }

  /**
   * {@inheritDoc}
   */
  public function countFacilitiesByType(string $organizationId): array
  {
    $counts = $this->facilityRepository->countByTypeForOrganizationId(
      FacilityOrganizationId::fromString($organizationId),
      includeArchived: true,
    );
    $normalizedCounts = [];

    foreach (FacilityType::cases() as $type) {
      $normalizedCounts[$type->value] = $counts[$type->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * {@inheritDoc}
   */
  public function countFacilitiesCreatedByDay(
    string $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
  ): array {
    return $this->facilityRepository->countByCreatedDayForOrganizationId(
      organizationId: FacilityOrganizationId::fromString($organizationId),
      createdAtFrom: $createdAtFrom,
      createdAtTo: $createdAtTo,
      timeZone: $timeZone,
      type: $type,
    );
  }
  // #endregion
}
