<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class TrustedDevicePermissionContributionAdapter
 *
 * Publishes TrustedDevice-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class TrustedDevicePermissionContributionAdapter implements PermissionCatalogContributionPort
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
      new PermissionDefinition('trusted_devices.create', 'Create trusted devices', 32),
      new PermissionDefinition('trusted_devices.read', 'View trusted devices', 33),
      new PermissionDefinition('trusted_devices.revoke', 'Revoke trusted devices', 34),
      new PermissionDefinition('trusted_devices.*', 'All trusted device operations', 35),
    ];
  }
  // #endregion
}
