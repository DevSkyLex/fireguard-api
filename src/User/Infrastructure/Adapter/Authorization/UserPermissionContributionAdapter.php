<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class UserPermissionContributionAdapter
 *
 * Publishes User-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class UserPermissionContributionAdapter implements PermissionCatalogContributionPort
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
      new PermissionDefinition('users.create', 'Create new users', 1),
      new PermissionDefinition('users.read', 'View user information', 2),
      new PermissionDefinition('users.update', 'Modify user data', 3),
      new PermissionDefinition('users.delete', 'Delete users', 4),
      new PermissionDefinition('users.*', 'All user operations', 5),
      new PermissionDefinition('profile.read', 'View own profile', 19),
      new PermissionDefinition('profile.update', 'Update own profile', 20),
    ];
  }
  // #endregion
}
