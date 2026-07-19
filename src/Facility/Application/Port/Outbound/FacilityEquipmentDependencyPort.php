<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

/**
 * Port FacilityEquipmentDependencyPort.
 *
 * Outbound contract the Facility module uses to ask the Equipment module whether
 * a facility still has active (non-decommissioned, published) equipment assigned
 * to it — a dependent that archiving the facility would orphan.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityEquipmentDependencyPort
{
  // #region Methods
  /**
   * Method hasActiveEquipmentInFacility.
   *
   * Returns true when at least one active (non-decommissioned, published)
   * equipment item is assigned to the facility.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @return bool whether active equipment is assigned to the facility
   */
  public function hasActiveEquipmentInFacility(string $organizationId, string $facilityId): bool;
  // #endregion
}
