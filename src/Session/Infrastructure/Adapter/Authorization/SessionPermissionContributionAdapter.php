<?php

declare(strict_types=1);

namespace Session\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class SessionPermissionContributionAdapter
 *
 * Publishes Session-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class SessionPermissionContributionAdapter implements PermissionCatalogContributionPort
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
      new PermissionDefinition('sessions.read', 'View own sessions', 21),
      new PermissionDefinition('sessions.revoke', 'Revoke own sessions', 22),
    ];
  }
  // #endregion
}
