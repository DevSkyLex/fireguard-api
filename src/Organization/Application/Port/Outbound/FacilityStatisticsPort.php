<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port FacilityStatisticsPort.
 *
 * Provides facility statistics for organization dashboards without
 * coupling the Organization module to the Facility repository.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityStatisticsPort
{
  // #region Methods
  /**
   * Method countActiveFacilities.
   *
   * Counts active (non-archived) facilities for an organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the active facility count
   */
  public function countActiveFacilities(string $organizationId): int;
  // #endregion
}
