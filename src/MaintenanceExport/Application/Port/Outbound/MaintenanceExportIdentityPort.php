<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\Port\Outbound;

/**
 * Interface MaintenanceExportIdentityPort
 * Owning-module validation never exposes contacts or crosses organization boundaries.
 *
 * @category Port
 */
interface MaintenanceExportIdentityPort
{
  // #region Methods
  /**
   * Method exists
   *
   * @param string $organizationId owning scope
   * @param string $resourceType customer, site or equipment
   * @param string $resourceId canonical UUID
   *
   * @return bool same-organization retained identity exists
   */
  public function exists(string $organizationId, string $resourceType, string $resourceId): bool;
  // #endregion
}
