<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Interface InterventionSiteNamingPort.
 *
 * Cross-module outbound port toward Facility, implemented by
 * `Facility\Infrastructure\Adapter\Intervention\InterventionSiteNamingAdapter`.
 * Resolves site (facility) identifiers into display names for the statistics
 * endpoint's `bySite` breakdown — mirrors
 * `Equipment\Application\Port\Outbound\FacilityNamingPort`.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionSiteNamingPort
{
  // #region Methods
  /**
   * Method findNamesByIds.
   *
   * Resolves the display name of each given site, in one round trip.
   *
   * Unknown or inaccessible identifiers are absent from the result rather than
   * mapped to an empty string; callers render nothing for them, which is
   * honest — a name that could not be resolved is not a blank name.
   *
   * @since 1.0.0
   *
   * @param list<string> $siteIds the site (facility) identifiers to resolve
   *
   * @return array<string,string> display name keyed by site identifier
   */
  public function findNamesByIds(array $siteIds): array;
  // #endregion
}
