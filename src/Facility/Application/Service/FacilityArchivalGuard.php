<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityInspectionDependencyPort, FacilityRepositoryPort};
use Facility\Domain\Exception\FacilityHasActiveDependentsException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};

/**
 * Service FacilityArchivalGuard.
 *
 * Enforces the "no active dependents" rule for facility archival across every
 * write surface: a facility may only be archived once it has no active child
 * facility (anywhere in its sub-tree), no active equipment, and no in-progress
 * inspection — otherwise archiving would orphan a live dependent.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityArchivalGuard implements FacilityArchivalGuardPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityArchivalGuard class.
   *
   * @since 1.0.0
   *
   * @param FacilityRepositoryPort $facilityRepository the facility repository port
   * @param FacilityEquipmentDependencyPort $equipmentDependency the equipment dependency port
   * @param FacilityInspectionDependencyPort $inspectionDependency the inspection dependency port
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityEquipmentDependencyPort $equipmentDependency,
    private FacilityInspectionDependencyPort $inspectionDependency,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertNoActiveDependents.
   * {@inheritdoc}
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   */
  public function assertNoActiveDependents(string $organizationId, string $facilityId): void
  {
    // Existence probe only (no sub-tree hydration); draft intervention scratchpad
    // records do not count, matching the equipment/inspection dependency checks.
    $hasActiveDescendants = $this->facilityRepository->hasActiveDescendants(
      FacilityOrganizationId::fromString($organizationId),
      FacilityId::fromString($facilityId),
    );

    if ($hasActiveDescendants) {
      throw FacilityHasActiveDependentsException::withActiveChildFacilities($facilityId);
    }

    if ($this->equipmentDependency->hasActiveEquipmentInFacility($organizationId, $facilityId)) {
      throw FacilityHasActiveDependentsException::withActiveEquipment($facilityId);
    }

    if ($this->inspectionDependency->hasActiveInspectionInFacility($organizationId, $facilityId)) {
      throw FacilityHasActiveDependentsException::withActiveInspections($facilityId);
    }
  }
  // #endregion
}
