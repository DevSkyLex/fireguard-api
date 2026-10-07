<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Interface InterventionEconomicScopePort
 *
 * Resolves a bounded live facility scope through its owner instead of joining sibling persistence.
 *
 * @category Port
 */
interface InterventionEconomicScopePort
{
  /**
   * Method facilityIds
   *
   * Uses published hierarchy including archived sites. Unknown or foreign filters yield no matches; more than 10000 locations requires a narrower scope.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param ?string $siteId optional root site filter
   * @param ?string $customerId optional internal client filter
   *
   * @return list<string> root and descendant location identifiers
   */
  public function facilityIds(string $organizationId, ?string $siteId, ?string $customerId): array;
}
