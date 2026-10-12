<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class AuthorizationPermissionContributionAdapter
 *
 * Publishes Authorization-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class AuthorizationPermissionContributionAdapter implements PermissionCatalogContributionPort
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
      new PermissionDefinition('roles.create', 'Create roles', 11),
      new PermissionDefinition('roles.read', 'View roles', 12),
      new PermissionDefinition('roles.update', 'Modify roles', 13),
      new PermissionDefinition('roles.delete', 'Delete roles', 14),
      new PermissionDefinition('roles.assign', 'Assign roles to users', 15),
      new PermissionDefinition('roles.*', 'All role operations', 16),
      new PermissionDefinition('permissions.read', 'View permissions', 17),
      new PermissionDefinition('permissions.manage', 'Manage permissions', 18),
      new PermissionDefinition('*.*', 'Full system access (super admin)', 44),
    ];
  }
  // #endregion
}
