<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Catalog;

use Authorization\Infrastructure\Catalog\PermissionCatalog;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_unique;
use function count;
use function in_array;
use function str_contains;
use function str_starts_with;
use function strpos;
use function substr;

/**
 * Test PermissionCatalogTest.
 *
 * This catalog is the single source fixtures, console commands and the docs
 * all read from. A duplicate entry would silently overwrite a permission at
 * seed time, and a missing wildcard would lock the super admin out — so the
 * shape, the uniqueness and the wildcard family are pinned rather than left
 * to review.
 *
 * @category Catalog Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PermissionCatalog::class)]
final class PermissionCatalogTest extends TestCase
{
  // #region Providers
  /**
   * @return iterable<string, array{string}>
   */
  public static function expectedPermissionProvider(): iterable
  {
    yield 'user creation' => ['users.create'];
    yield 'oauth client deletion' => ['clients.delete'];
    yield 'role assignment' => ['roles.assign'];
    yield 'permission management' => ['permissions.manage'];
    yield 'self-service profile' => ['profile.update'];
    yield 'session revocation' => ['sessions.revoke'];
    yield 'totp setup' => ['otp_totp.setup'];
    yield 'trusted device revocation' => ['trusted_devices.revoke'];
    yield 'tenant creation' => ['tenants.create'];
    yield 'audit export' => ['audit.export'];
    yield 'super admin' => ['*.*'];
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testDefinitionsIsANonEmptyListOfNameAndDescriptionPairs(): void
  {
    $definitions = PermissionCatalog::definitions();

    self::assertNotEmpty($definitions);
    foreach ($definitions as $definition) {
      self::assertArrayHasKey('name', $definition);
      self::assertArrayHasKey('description', $definition);
      self::assertNotSame('', $definition['name']);
      self::assertNotSame('', $definition['description']);
    }
  }

  #[Test]
  #[DataProvider('expectedPermissionProvider')]
  public function testDefinitionsExposeTheCoreAuthorizationVocabulary(string $permission): void
  {
    self::assertContains($permission, array_column(PermissionCatalog::definitions(), 'name'));
  }

  #[Test]
  public function testDefinitionsNeverRepeatAPermissionName(): void
  {
    $names = array_column(PermissionCatalog::definitions(), 'name');

    self::assertCount(count(array_unique($names)), $names);
  }

  #[Test]
  public function testEveryWildcardHasAtLeastOneConcreteSiblingScope(): void
  {
    $names = array_column(PermissionCatalog::definitions(), 'name');

    foreach ($names as $permission) {
      if ('*.*' === $permission || !str_contains($permission, '.*')) {
        continue;
      }

      $prefix = substr($permission, 0, (int) strpos($permission, '.'));
      $hasConcrete = false;
      foreach ($names as $candidate) {
        if ($candidate !== $permission && str_starts_with($candidate, $prefix . '.')) {
          $hasConcrete = true;

          break;
        }
      }

      self::assertTrue($hasConcrete, $permission . ' has no concrete sibling scope.');
    }
  }

  #[Test]
  public function testDefinitionsAreStableAcrossCalls(): void
  {
    self::assertSame(PermissionCatalog::definitions(), PermissionCatalog::definitions());
  }

  #[Test]
  public function testDefinitionsDoNotLeakAnUnscopedWildcardBesidesTheSuperAdminOne(): void
  {
    $names = array_column(PermissionCatalog::definitions(), 'name');

    self::assertTrue(in_array('*.*', $names, true));
    self::assertNotContains('*', $names);
  }
  // #endregion
}
