<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

/**
 * Port FacilityAvailabilityPort.
 *
 * Publishes whether an organization already has a published active facility.
 *
 * @category Port
 */
interface FacilityAvailabilityPort
{
  // #region Methods
  /**
   * Method hasFacility.
   */
  public function hasFacility(string $organizationId): bool;
  // #endregion
}
