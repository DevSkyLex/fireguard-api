<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Catalog;

use Authorization\Infrastructure\Catalog\RoleCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_unique;
use function array_values;

/**
 * Test RoleCatalogTest.
 *
 * @category Catalog Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleCatalog::class)]
final class RoleCatalogTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuperAdminIncludesWildcard(): void
  {
    $permissions = RoleCatalog::superAdminPermissionNames();

    self::assertContains('*.*', $permissions);
    self::assertSame(['*.*'], $permissions);
  }

  #[Test]
  public function testAdminPermissionsCoverManagementScopes(): void
  {
    $permissions = RoleCatalog::adminPermissionNames();

    self::assertNotContains('*.*', $permissions);
    self::assertSame(array_values(array_unique($permissions)), $permissions);

    foreach ([
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
    ] as $expected) {
      self::assertContains($expected, $permissions);
    }

    self::assertCount(21, $permissions);
  }

  #[Test]
  public function testUserPermissionsAreSelfServiceOnlyAndSubsetOfAdmin(): void
  {
    $permissions = RoleCatalog::userPermissionNames();

    self::assertSame([
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
    ], $permissions);

    self::assertNotContains('users.*', $permissions);
    self::assertNotContains('audit.read', $permissions);
    self::assertEmpty(array_diff($permissions, RoleCatalog::adminPermissionNames()));
  }
  // #endregion
}
