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
 *
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
   * @return list<array{equipmentId: string, name: string, status: string, x: float, y: float}> the equipment pinned on this plan
   */
  public function findEquipmentPlacedOnPlan(string $organizationId, string $attachmentId): array;
  // #endregion
}
