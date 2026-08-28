<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Application\Contract\Search\OrganizationSearchHit;

/**
 * Port FacilitySearchPort.
 *
 * Exposes the owning module's free-text search to the Organization module's
 * global search — matches facility records by name, code and address.
 * Third occurrence of the naming/statistics outbound-port pattern: the port
 * lives here, its adapter lives in the owning module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilitySearchPort
{
  // #region Methods
  /**
   * Method search.
   *
   * Runs a case-insensitive, organization-scoped free-text search and
   * returns at most $limit hits, most recently updated first.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $term the raw search term (wildcards are escaped by the adapter)
   * @param int $limit the maximum number of hits to return
   *
   * @return list<OrganizationSearchHit> the matching hits
   */
  public function search(string $organizationId, string $term, int $limit): array;
  // #endregion
}
