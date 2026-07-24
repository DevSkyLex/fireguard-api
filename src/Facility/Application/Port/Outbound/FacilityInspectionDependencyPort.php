<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

/**
 * Port FacilityInspectionDependencyPort.
 *
 * Outbound contract the Facility module uses to ask the Inspection module whether
 * a facility still has in-progress (draft or submitted, published) inspections —
 * a dependent that archiving the facility would orphan.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityInspectionDependencyPort
{
  // #region Methods
  /**
   * Method hasActiveInspectionInFacility.
   *
   * Returns true when at least one in-progress (draft or submitted, published)
   * inspection targets the facility.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @return bool whether an in-progress inspection targets the facility
   */
  public function hasActiveInspectionInFacility(string $organizationId, string $facilityId): bool;
  // #endregion
}
