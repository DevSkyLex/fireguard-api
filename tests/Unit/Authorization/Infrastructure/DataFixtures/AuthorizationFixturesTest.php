<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\DataFixtures;

use Authorization\Application\Contract\Catalog\{PermissionDefinition, RoleDefinition};
use Authorization\Application\Port\Inbound\{PermissionCatalogContributionPort, RoleCatalogContributionPort};
use Authorization\Infrastructure\Catalog\{PermissionCatalog, RoleCatalog};
use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuthorizationFixturesTest.
 *
 * @category DataFixtures Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuthorizationFixtures::class)]
final class AuthorizationFixturesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetGroupsReturnsAuthorizationGroup(): void
  {
    self::assertSame(['authorization', 'auth-seed'], AuthorizationFixtures::getGroups());
  }

  #[Test]
  public function testForeignDefinitionsAreSeededWithoutProductSpecificRolesOrSubjects(): void
  {
    $permissions = new class () implements PermissionCatalogContributionPort {
      public static function definitions(): array
      {
        return [new PermissionDefinition('reviews.read', 'View reviews')];
      }
    };
    $roles = new class () implements RoleCatalogContributionPort {
      public static function definitions(): array
      {
        return [new RoleDefinition('reviewer', 'Review access', ['reviews.read'], isSystem: false, reference: 'reviewer-role')];
      }
    };
    $permissionCatalog = new PermissionCatalog([$permissions]);
    $roleCatalog = new RoleCatalog([$roles]);
    $roleCatalog->validatePermissions($permissionCatalog);
    $persisted = [];
    $manager = $this->createMock(ObjectManager::class);
    $manager->expects(self::exactly(2))->method('persist')->willReturnCallback(static function (object $record) use (&$persisted): void {
      $persisted[] = $record;
    });
    $manager->expects(self::exactly(2))->method('flush');
    $fixture = new AuthorizationFixtures($permissionCatalog, $roleCatalog);
    $references = $this->createMock(ReferenceRepository::class);
    $references->expects(self::once())->method('addReference')->with('reviewer-role', self::isInstanceOf(RoleRecord::class));
    $fixture->setReferenceRepository($references);
    $fixture->load($manager);

    self::assertInstanceOf(PermissionRecord::class, $persisted[0]);
    self::assertInstanceOf(RoleRecord::class, $persisted[1]);
    self::assertSame('reviews.read', $persisted[0]->name);
    self::assertSame('View reviews', $persisted[0]->description);
    self::assertSame('reviewer', $persisted[1]->name);
    self::assertFalse($persisted[1]->isSystem);
    self::assertSame([$persisted[0]], $persisted[1]->permissions->toArray());
  }
  // #endregion
}
