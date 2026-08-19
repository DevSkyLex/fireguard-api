<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

/**
 * Port FacilityInterventionDependencyPort.
 *
 * Outbound contract the Facility module uses to ask the Intervention module whether
 * a facility still has active (not closed) interventions targeting it as their
 * site — a dependent that archiving the facility would orphan.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityInterventionDependencyPort
{
  // #region Methods
  /**
   * Method hasActiveInterventionInFacility.
   *
   * Returns true when at least one active (not closed) intervention targets the
   * facility as its site.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   *
   * @return bool whether an active intervention targets the facility
   */
  public function hasActiveInterventionInFacility(string $organizationId, string $facilityId): bool;
  // #endregion
}
