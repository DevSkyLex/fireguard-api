<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class OAuthPermissionContributionAdapter
 *
 * Publishes OAuth-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class OAuthPermissionContributionAdapter implements PermissionCatalogContributionPort
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
      new PermissionDefinition('clients.create', 'Create OAuth2 clients', 6),
      new PermissionDefinition('clients.read', 'View OAuth2 clients', 7),
      new PermissionDefinition('clients.update', 'Modify OAuth2 clients', 8),
      new PermissionDefinition('clients.delete', 'Delete OAuth2 clients', 9),
      new PermissionDefinition('clients.*', 'All client operations', 10),
    ];
  }
  // #endregion
}
