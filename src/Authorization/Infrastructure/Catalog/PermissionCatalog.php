<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Catalog;

/**
 * PermissionCatalog.
 *
 * Central list of permission definitions to keep fixtures,
 * commands, and documentation in sync.
 */
final class PermissionCatalog
{
  /**
   * @return list<array{name: string, description: string}>
   */
  public static function definitions(): array
  {
    return [
      // User management
      ['name' => 'users.create', 'description' => 'Create new users'],
      ['name' => 'users.read', 'description' => 'View user information'],
      ['name' => 'users.update', 'description' => 'Modify user data'],
      ['name' => 'users.delete', 'description' => 'Delete users'],
      ['name' => 'users.*', 'description' => 'All user operations'],

      // Client (OAuth2) management
      ['name' => 'clients.create', 'description' => 'Create OAuth2 clients'],
      ['name' => 'clients.read', 'description' => 'View OAuth2 clients'],
      ['name' => 'clients.update', 'description' => 'Modify OAuth2 clients'],
      ['name' => 'clients.delete', 'description' => 'Delete OAuth2 clients'],
      ['name' => 'clients.*', 'description' => 'All client operations'],

      // Role management
      ['name' => 'roles.create', 'description' => 'Create roles'],
      ['name' => 'roles.read', 'description' => 'View roles'],
      ['name' => 'roles.update', 'description' => 'Modify roles'],
      ['name' => 'roles.delete', 'description' => 'Delete roles'],
      ['name' => 'roles.assign', 'description' => 'Assign roles to users'],
      ['name' => 'roles.*', 'description' => 'All role operations'],

      // Permission management
      ['name' => 'permissions.read', 'description' => 'View permissions'],
      ['name' => 'permissions.manage', 'description' => 'Manage permissions'],

      // Profile (self-service)
      ['name' => 'profile.read', 'description' => 'View own profile'],
      ['name' => 'profile.update', 'description' => 'Update own profile'],

      // Session management
      ['name' => 'sessions.read', 'description' => 'View own sessions'],
      ['name' => 'sessions.revoke', 'description' => 'Revoke own sessions'],

      // OTP management
      ['name' => 'otp_config.read', 'description' => 'View OTP configuration'],
      ['name' => 'otp_challenges.create', 'description' => 'Create OTP challenges'],
      ['name' => 'otp_challenges.read', 'description' => 'View OTP challenges'],
      ['name' => 'otp_challenges.verify', 'description' => 'Verify OTP challenges'],
      ['name' => 'otp_challenges.resend', 'description' => 'Resend OTP challenges'],
      ['name' => 'otp_challenges.*', 'description' => 'All OTP challenge operations'],
      ['name' => 'otp_totp.setup', 'description' => 'Setup TOTP'],

      // Trusted device management
      ['name' => 'trusted_devices.create', 'description' => 'Create trusted devices'],
      ['name' => 'trusted_devices.read', 'description' => 'View trusted devices'],
      ['name' => 'trusted_devices.revoke', 'description' => 'Revoke trusted devices'],
      ['name' => 'trusted_devices.*', 'description' => 'All trusted device operations'],

      // Tenant management
      ['name' => 'tenants.create', 'description' => 'Create tenants'],
      ['name' => 'tenants.read', 'description' => 'View tenants'],
      ['name' => 'tenants.update', 'description' => 'Modify tenants'],
      ['name' => 'tenants.delete', 'description' => 'Delete tenants'],
      ['name' => 'tenants.*', 'description' => 'All tenant operations'],

      // Audit management
      ['name' => 'audit.read', 'description' => 'View audit events'],
      ['name' => 'audit.export', 'description' => 'Export audit events'],
      ['name' => 'audit.*', 'description' => 'All audit operations'],

      // Super admin wildcard
      ['name' => '*.*', 'description' => 'Full system access (super admin)'],
    ];
  }
}
