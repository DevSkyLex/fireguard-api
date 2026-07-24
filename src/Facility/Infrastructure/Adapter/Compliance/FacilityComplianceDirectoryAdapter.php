<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Compliance;

use Compliance\Application\Port\Outbound\ComplianceFacilityDirectoryPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\FacilityOrganizationId;

use function array_map;

/**
 * Adapter FacilityComplianceDirectoryAdapter.
 *
 * Implements the Compliance module's facility directory port using the
 * Facility module's own repository — mirrors
 * `Facility\Infrastructure\Adapter\Organization\FacilityStatisticsAdapter`.
 * Includes archived facilities: an archived facility's compliance history
 * (and any equipment/non-conformities still attributed to it) must remain
 * visible in the regulatory register.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityComplianceDirectoryAdapter implements ComplianceFacilityDirectoryPort
{
  // #region Constants
  /**
   * Constant MAX_FACILITIES.
   *
   * Safety cap on the number of facilities read for a single register
   * generation.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_FACILITIES = 5000;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FacilityRepositoryPort $facilityRepository the facility repository port
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function listFacilities(string $organizationId): array
  {
    $facilities = $this->facilityRepository->findByOrganizationId(
      organizationId: FacilityOrganizationId::fromString($organizationId),
      includeArchived: true,
      limit: self::MAX_FACILITIES,
    );

    return array_map(static fn (Facility $facility): array => [
      'id' => (string) $facility->id(),
      'name' => (string) $facility->name(),
      'type' => $facility->type()->value,
      'status' => $facility->status()->value,
      'parentFacilityId' => $facility->parentFacilityId()?->__toString(),
    ], $facilities);
  }
  // #endregion
}
