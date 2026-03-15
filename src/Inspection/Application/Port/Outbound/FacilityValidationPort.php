<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use InvalidArgumentException;

/**
 * Port FacilityValidationPort.
 *
 * Validates that a facility exists, belongs to the expected organization,
 * and is active. This abstracts the Facility module from the Inspection module,
 * maintaining proper module isolation in hexagonal architecture.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityValidationPort
{
  // #region Methods
  /**
   * Method assertFacilityIsUsable.
   *
   * Verifies that the given facility exists, belongs to the specified
   * organization, and is in an active (non-archived) state.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   * @param string $organizationId the expected organization identifier
   *
   * @throws InvalidArgumentException when the facility is not found, belongs to another organization, or is archived
   */
  public function assertFacilityIsUsable(string $facilityId, string $organizationId): void;
  // #endregion
}
