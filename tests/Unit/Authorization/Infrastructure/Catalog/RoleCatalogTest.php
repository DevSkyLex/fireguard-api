<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Catalog;

use Authorization\Application\Contract\Catalog\RoleDefinition;
use Authorization\Application\Port\Inbound\RoleCatalogContributionPort;
use Authorization\Infrastructure\Catalog\{PermissionCatalog, RoleCatalog};
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Tests\Support\AuthorizationCatalogFixture;

use function array_column;

/**
 * Class RoleCatalogTest
 *
 * Pins installed bootstrap compatibility and open-contribution failure behavior.
 *
 * @category Catalog Tests
 */
#[CoversClass(RoleCatalog::class)]
final class RoleCatalogTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInstalledRoleNamesGrantsDescriptionsAndReferencesRemainStable(): void
  {
    $catalog = AuthorizationCatalogFixture::roles();
    $definitions = $catalog->definitions();
    self::assertSame(['super_admin', 'admin', 'user'], array_column($definitions, 'name'));
    self::assertSame(['role-super-admin', 'role-admin', 'role-user'], array_column($definitions, 'reference'));
    self::assertSame([
      'Full system access with all permissions',
      'Administrative access for user and client management',
      'Standard user access with profile and session management',
    ], array_column($definitions, 'description'));
    self::assertSame(['*.*'], $definitions[0]->permissionNames);
    self::assertSame([
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
    ], $definitions[1]->permissionNames);
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
    ], $definitions[2]->permissionNames);
    self::assertSame('user', $catalog->defaultRoleName());
    $catalog->validatePermissions(AuthorizationCatalogFixture::permissions());
  }

  #[Test]
  public function testForeignContributionChangesDefaultWithoutEditingRegistry(): void
  {
    $contribution = new class () implements RoleCatalogContributionPort {
      public static function definitions(): array
      {
        return [new RoleDefinition('reviewer', 'Review access', [], defaultForNewUsers: true)];
      }
    };
    $catalog = new RoleCatalog([$contribution]);
    self::assertSame('reviewer', $catalog->defaultRoleName());
    self::assertSame(['reviewer'], array_column($catalog->definitions(), 'name'));
  }

  #[Test]
  public function testDuplicateRoleKeysFailComposition(): void
  {
    $contribution = new class () implements RoleCatalogContributionPort {
      public static function definitions(): array
      {
        return [new RoleDefinition('reviewer', 'Review access', [])];
      }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Duplicate role catalog key: reviewer');
    new RoleCatalog([$contribution, $contribution]);
  }

  #[Test]
  public function testUnregisteredGrantFailsValidationEvenWithWildcardRegistered(): void
  {
    $contribution = new class () implements RoleCatalogContributionPort {
      public static function definitions(): array
      {
        return [new RoleDefinition('reviewer', 'Review access', ['removed.read'])];
      }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Unknown permission catalog key removed.read in role reviewer');
    new RoleCatalog([$contribution])->validatePermissions(AuthorizationCatalogFixture::permissions());
  }

  #[Test]
  public function testMultipleDefaultPoliciesFailComposition(): void
  {
    $contribution = new class () implements RoleCatalogContributionPort {
      public static function definitions(): array
      {
        return [
          new RoleDefinition('reviewer', 'Review', [], defaultForNewUsers: true),
          new RoleDefinition('editor', 'Edit', [], defaultForNewUsers: true),
        ];
      }
    };
    $this->expectException(LogicException::class);
    new RoleCatalog([$contribution]);
  }

  #[Test]
  public function testDuplicateFixtureReferencesFailComposition(): void
  {
    $contribution = new class () implements RoleCatalogContributionPort {
      public static function definitions(): array
      {
        return [
          new RoleDefinition('reviewer', 'Review', [], reference: 'same-reference'),
          new RoleDefinition('editor', 'Edit', [], reference: 'same-reference'),
        ];
      }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Duplicate role fixture reference: same-reference');
    new RoleCatalog([$contribution]);
  }

  #[Test]
  public function testEmptyRegistryHasNoImplicitDefault(): void
  {
    $catalog = new RoleCatalog([]);
    self::assertSame([], $catalog->definitions());
    self::assertNull($catalog->defaultRoleName());
    $catalog->validatePermissions(new PermissionCatalog([]));
  }
  // #endregion
}
