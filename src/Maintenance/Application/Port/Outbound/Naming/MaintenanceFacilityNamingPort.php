<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Naming;

/**
 * Port MaintenanceFacilityNamingPort.
 *
 * Resolves facility identifiers into display names, so a maintenance
 * schedule export can name where the tracked equipment sits — mirrors
 * `Inspection\Application\Port\Outbound\FacilityNamingPort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceFacilityNamingPort
{
  // #region Methods
  /**
   * Method findNamesByIds.
   *
   * Resolves the display name of each given facility, in one round trip.
   * Unresolvable identifiers are absent from the result rather than mapped
   * to an empty string.
   *
   * @since 1.0.0
   *
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> display name keyed by facility identifier
   */
  public function findNamesByIds(array $facilityIds): array;
  // #endregion
}
