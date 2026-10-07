<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Published facility identifiers underneath a customer's root sites.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityCustomerScopePort
{
  /**
   * @since 1.0.0
   *
   * @return list<string>
   */
  public function findPublishedIdsForCustomer(string $organizationId, string $customerId): array;
}
