<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

/**
 * Port FacilityEquipmentPlanPositionPort.
 *
 * Lists the equipment pinned on one floor plan attachment, for the plan
 * overlay read (`GetFacilityPlanOverlayHandler`). Implemented by the
 * Equipment module, mirroring the direction of
 * `FacilityEquipmentDependencyPort` — Facility declares what it needs from
 * Equipment's data, Equipment's Infrastructure supplies it.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityEquipmentPlanPositionPort
{
  // #region Methods
  /**
   * Method findEquipmentPlacedOnPlan.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $attachmentId the floor plan attachment identifier
   *
   * @return list<array{equipmentId: string, facilityId: ?string, type: string, serialNumber: string|null, locationLabel: string|null, status: string, x: float, y: float, invalidPosition: bool}> the equipment pinned on this plan
   */
  public function findEquipmentPlacedOnPlan(string $organizationId, string $attachmentId): array;

  /**
   * Method findEquipmentForFacilities
   *
   * Reads published, non-decommissioned equipment assigned to the supplied
   * organization-scoped hierarchy bindings, including rows without a usable pin.
   * No facility hierarchy is queried across the module boundary.
   *
   * @access public
   *
   * @param string $organizationId organization owning the equipment
   * @param list<array{floorId: string, facilityId: string}> $facilityBindings each facility's closest floor
   *
   * @return list<array{floorId: string, equipmentId: string, facilityId: string, type: string, serialNumber: ?string, locationLabel: ?string, status: string, position: ?array{attachmentId: string, x: float, y: float}, invalidPosition: bool}> equipment with its original assignment and validated normalized pin
   */
  public function findEquipmentForFacilities(string $organizationId, array $facilityBindings): array;
  // #endregion
}
