<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Organization;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\FacilityOrganizationId;
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
  public function countActiveFacilities(string $organizationId): int
  {
    return $this->facilityRepository->countActiveByOrganizationId(
      FacilityOrganizationId::fromString($organizationId),
    );
  }
  // #endregion
}
