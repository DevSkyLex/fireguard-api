<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

/**
 * Port FacilityNamingPort.
 *
 * Resolves facility identifiers into display names, so an inspection can name
 * where it took place.
 *
 * Separate from {@see FacilityValidationPort} for the same reason its Equipment
 * counterpart is: validation throws, naming reads.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityNamingPort
{
  // #region Methods
  /**
   * Method findNamesByIds.
   *
   * Resolves the display name of each given facility, in one round trip.
   *
   * Unresolvable identifiers are absent from the result rather than mapped to
   * an empty string.
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
