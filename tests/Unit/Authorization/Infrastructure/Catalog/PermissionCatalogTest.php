<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Catalog;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;
use Authorization\Domain\ValueObject\PermissionName;
use Authorization\Infrastructure\Catalog\PermissionCatalog;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Tests\Support\AuthorizationCatalogFixture;

use function array_column;
use function array_map;
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
    $definitions = AuthorizationCatalogFixture::permissions()->definitions();

    self::assertNotEmpty($definitions);
    foreach ($definitions as $definition) {
      self::assertNotSame('', $definition->name);
      self::assertNotSame('', $definition->description);
    }
  }

  #[Test]
  #[DataProvider('expectedPermissionProvider')]
  public function testDefinitionsExposeTheCoreAuthorizationVocabulary(string $permission): void
  {
    self::assertContains($permission, array_column(AuthorizationCatalogFixture::permissions()->definitions(), 'name'));
  }

  #[Test]
  public function testDefinitionsNeverRepeatAPermissionName(): void
  {
    $names = array_column(AuthorizationCatalogFixture::permissions()->definitions(), 'name');

    self::assertCount(count(array_unique($names)), $names);
  }

  #[Test]
  public function testEveryWildcardHasAtLeastOneConcreteSiblingScope(): void
  {
    $names = array_column(AuthorizationCatalogFixture::permissions()->definitions(), 'name');

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
    $catalog = AuthorizationCatalogFixture::permissions();
    self::assertSame($catalog->definitions(), $catalog->definitions());
  }

  #[Test]
  public function testDefinitionsDoNotLeakAnUnscopedWildcardBesidesTheSuperAdminOne(): void
  {
    $names = array_column(AuthorizationCatalogFixture::permissions()->definitions(), 'name');

    self::assertTrue(in_array('*.*', $names, true));
    self::assertNotContains('*', $names);
  }

  #[Test]
  public function testExternalContributorAddsVocabularyWithoutChangingRegistry(): void
  {
    $contribution = new class () implements PermissionCatalogContributionPort {
      public static function definitions(): array
      {
        return [new PermissionDefinition('reviews.read', 'View reviews')];
      }
    };
    $catalog = new PermissionCatalog([$contribution]);
    self::assertSame(['reviews.read'], array_column($catalog->definitions(), 'name'));
    self::assertTrue($catalog->contains('reviews.read'));
    self::assertFalse($catalog->contains('reviews.write'));
  }

  #[Test]
  public function testDuplicateKeysFailInsteadOfOverwritingAnotherOwner(): void
  {
    $contribution = new class () implements PermissionCatalogContributionPort {
      public static function definitions(): array
      {
        return [new PermissionDefinition('reviews.read', 'View reviews')];
      }
    };
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Duplicate permission catalog key: reviews.read');
    new PermissionCatalog([$contribution, $contribution]);
  }

  #[Test]
  public function testMissingContributorDoesNotPreventHistoricalPermissionHydration(): void
  {
    $catalog = new PermissionCatalog([]);
    self::assertFalse($catalog->contains('removed.read'));
    self::assertSame('removed.read', new PermissionName('removed.read')->value);
    self::assertTrue(new PermissionName('removed.*')->matches(new PermissionName('removed.read')));
  }

  #[Test]
  public function testRegistrationUsesExactKeysAndDoesNotExpandWildcard(): void
  {
    $catalog = AuthorizationCatalogFixture::permissions();
    self::assertTrue($catalog->contains('*.*'));
    self::assertFalse($catalog->contains('removed.read'));
  }

  #[Test]
  public function testEmptyRegistryDoesNotInstallImplicitProductContributions(): void
  {
    self::assertSame([], new PermissionCatalog([])->definitions());
  }

  #[Test]
  public function testInstalledWireKeysDescriptionsAndOrderRemainStable(): void
  {
    $actual = array_map(static fn (PermissionDefinition $definition): array => [$definition->name, $definition->description], AuthorizationCatalogFixture::permissions()->definitions());
    self::assertSame([
      ['users.create', 'Create new users'],
      ['users.read', 'View user information'],
      ['users.update', 'Modify user data'],
      ['users.delete', 'Delete users'],
      ['users.*', 'All user operations'],
      ['clients.create', 'Create OAuth2 clients'],
      ['clients.read', 'View OAuth2 clients'],
      ['clients.update', 'Modify OAuth2 clients'],
      ['clients.delete', 'Delete OAuth2 clients'],
      ['clients.*', 'All client operations'],
      ['roles.create', 'Create roles'],
      ['roles.read', 'View roles'],
      ['roles.update', 'Modify roles'],
      ['roles.delete', 'Delete roles'],
      ['roles.assign', 'Assign roles to users'],
      ['roles.*', 'All role operations'],
      ['permissions.read', 'View permissions'],
      ['permissions.manage', 'Manage permissions'],
      ['profile.read', 'View own profile'],
      ['profile.update', 'Update own profile'],
      ['sessions.read', 'View own sessions'],
      ['sessions.revoke', 'Revoke own sessions'],
      ['otp_config.read', 'View OTP configuration'],
      ['otp_challenges.create', 'Create OTP challenges'],
      ['otp_challenges.read', 'View OTP challenges'],
      ['otp_challenges.verify', 'Verify OTP challenges'],
      ['otp_challenges.resend', 'Resend OTP challenges'],
      ['otp_challenges.*', 'All OTP challenge operations'],
      ['otp_totp.setup', 'Setup TOTP'],
      ['otp_totp.confirm', 'Confirm TOTP setup'],
      ['otp_totp.disable', 'Disable TOTP'],
      ['trusted_devices.create', 'Create trusted devices'],
      ['trusted_devices.read', 'View trusted devices'],
      ['trusted_devices.revoke', 'Revoke trusted devices'],
      ['trusted_devices.*', 'All trusted device operations'],
      ['tenants.create', 'Create tenants'],
      ['tenants.read', 'View tenants'],
      ['tenants.update', 'Modify tenants'],
      ['tenants.delete', 'Delete tenants'],
      ['tenants.*', 'All tenant operations'],
      ['audit.read', 'View audit events'],
      ['audit.export', 'Export audit events'],
      ['audit.*', 'All audit operations'],
      ['*.*', 'Full system access (super admin)'],
    ], $actual);
  }

  #[Test]
  public function testContributorDiscoveryOrderDoesNotChangePublicationOrder(): void
  {
    $first = new class () implements PermissionCatalogContributionPort {
      public static function definitions(): array
      {
        return [new PermissionDefinition('first.read', 'First', 1)];
      }
    };
    $last = new class () implements PermissionCatalogContributionPort {
      public static function definitions(): array
      {
        return [new PermissionDefinition('last.read', 'Last', 2)];
      }
    };
    self::assertSame(
      array_column(new PermissionCatalog([$first, $last])->definitions(), 'name'),
      array_column(new PermissionCatalog([$last, $first])->definitions(), 'name'),
    );
  }
  // #endregion
}
