<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Interface InterventionSiteCustomerSnapshotPort
 *
 * Reads the site and internal customer's identity on the owning main connection.
 *
 * @category Port
 */
interface InterventionSiteCustomerSnapshotPort
{
  /**
   * Method snapshot
   *
   * Rejects unavailable or foreign sites; a null site legitimately has no customer.
   *
   * @access public
   *
   * @param string $organizationId publication organization
   * @param ?string $siteId intervention site identifier
   *
   * @return array{site:?array{id:string,name:string},customer:?array{id:string,name:string,contacts:list<array<string,mixed>>}}
   */
  public function snapshot(string $organizationId, ?string $siteId): array;
}
