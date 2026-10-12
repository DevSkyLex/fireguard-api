<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\RoleDefinition;
use Authorization\Application\Port\Inbound\RoleCatalogContributionPort;

/**
 * Class SystemRoleContributionAdapter
 *
 * Publishes the platform's installed bootstrap policy separately from reusable registry mechanics.
 *
 * @category Adapter
 */
final readonly class SystemRoleContributionAdapter implements RoleCatalogContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * Preserves system-role names, grants, references and default account assignment.
   *
   * @access public
   *
   * @return list<RoleDefinition> installed platform policy
   */
  public static function definitions(): array
  {
    return [
      new RoleDefinition('super_admin', 'Full system access with all permissions', ['*.*'], reference: 'role-super-admin', order: 1),
      new RoleDefinition('admin', 'Administrative access for user and client management', [
        'users.*',
        'clients.*',
        'roles.read',
        'roles.create',
        'roles.update',
        'roles.assign',
        'permissions.read',
        'tenants.create',
        'tenants.read',
        'profile.read',
        'profile.update',
        'sessions.read',
        'sessions.revoke',
        'audit.read',
        'audit.export',
        'otp_config.read',
        'otp_challenges.*',
        'otp_totp.setup',
        'otp_totp.confirm',
        'otp_totp.disable',
        'trusted_devices.*',
      ], reference: 'role-admin', order: 2),
      new RoleDefinition('user', 'Standard user access with profile and session management', [
        'profile.read',
        'profile.update',
        'sessions.read',
        'sessions.revoke',
        'otp_config.read',
        'otp_challenges.*',
        'otp_totp.setup',
        'otp_totp.confirm',
        'otp_totp.disable',
        'trusted_devices.*',
      ], reference: 'role-user', defaultForNewUsers: true, order: 3),
    ];
  }
  // #endregion
}
