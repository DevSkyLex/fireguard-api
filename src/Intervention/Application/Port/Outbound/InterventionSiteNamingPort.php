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
 * `$organizationId` scopes the lookup (Phase 5 review — the unscoped
 * signature let a caller in one organization resolve a facility name
 * belonging to another, a low-severity information leak since the
 * statistics gateway already restricts `$aggregate->topSites` to the
 * caller's own organization; the parameter closes the gap defensively at
 * the adapter, mirroring `InterventionMemberNamingPort::displayNamesFor()`'s
 * shape).
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
   * honest — a name that could not be resolved is not a blank name. A site
   * belonging to another organization resolves as unknown too.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization the caller is scoped to
   * @param list<string> $siteIds the site (facility) identifiers to resolve
   *
   * @return array<string,string> display name keyed by site identifier
   */
  public function findNamesByIds(string $organizationId, array $siteIds): array;
  // #endregion
}
