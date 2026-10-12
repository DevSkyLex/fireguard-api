<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class TenantPermissionContributionAdapter
 *
 * Publishes Tenant-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class TenantPermissionContributionAdapter implements PermissionCatalogContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * Keeps persisted keys, descriptions and publication order stable.
   *
   * @access public
   *
   * @return list<PermissionDefinition> owner vocabulary
   */
  public static function definitions(): array
  {
    return [
      new PermissionDefinition('tenants.create', 'Create tenants', 36),
      new PermissionDefinition('tenants.read', 'View tenants', 37),
      new PermissionDefinition('tenants.update', 'Modify tenants', 38),
      new PermissionDefinition('tenants.delete', 'Delete tenants', 39),
      new PermissionDefinition('tenants.*', 'All tenant operations', 40),
    ];
  }
  // #endregion
}
