<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Catalog;

/**
 * RoleCatalog.
 *
 * Defines default permission sets for system roles.
 */
final class RoleCatalog
{
  /**
   * @return list<string>
   */
  public static function superAdminPermissionNames(): array
  {
    return ['*.*'];
  }

  /**
   * @return list<string>
   */
  public static function adminPermissionNames(): array
  {
    return [
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
      'trusted_devices.*',
    ];
  }

  /**
   * @return list<string>
   */
  public static function userPermissionNames(): array
  {
    return [
      'profile.read',
      'profile.update',
      'sessions.read',
      'sessions.revoke',
      'otp_config.read',
      'otp_challenges.*',
      'otp_totp.setup',
      'trusted_devices.*',
    ];
  }
}
