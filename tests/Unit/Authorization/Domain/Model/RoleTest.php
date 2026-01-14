<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Model;

use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function md5;
use function substr;

/**
 * Test RoleTest.
 *
 * @category Domain Model Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Role::class)]
final class RoleTest extends TestCase
{
  // #region Factory Method Tests

  /**
   * Test role creation with valid data.
   */
  #[Test]
  public function testCreateRoleWithValidData(): void
  {
    $id = new RoleId('550e8400-e29b-41d4-a716-446655440000');
    $name = new RoleName('admin');
    $description = 'Administrator role';

    $role = Role::create(
      id: $id,
      name: $name,
      description: $description,
      isSystem: false,
    );

    $this->assertEquals($id->value, $role->id()->value);
    $this->assertEquals('admin', $role->name()->value);
    $this->assertEquals('Administrator role', $role->description());
    $this->assertFalse($role->isSystem());
    $this->assertNull($role->tenantId());
    $this->assertEmpty($role->permissions());
    $this->assertInstanceOf(DateTimeImmutable::class, $role->createdAt());
  }

  /**
   * Test creating a system role.
   */
  #[Test]
  public function testCreateSystemRole(): void
  {
    $id = new RoleId('550e8400-e29b-41d4-a716-446655440001');
    $name = new RoleName('super_admin');

    $role = Role::create(
      id: $id,
      name: $name,
      description: 'Super administrator',
      isSystem: true,
    );

    $this->assertTrue($role->isSystem());
  }

  // #endregion

  // #region Permission Management Tests

  /**
   * Test adding a permission to a role.
   */
  #[Test]
  public function testAddPermissionToRole(): void
  {
    $role = $this->createTestRole();
    $permission = $this->createTestPermission('users.create');

    $this->assertCount(0, $role->permissions());

    $role->addPermission($permission);

    $this->assertCount(1, $role->permissions());
    $this->assertContains($permission, $role->permissions());
    $this->assertNotNull($role->updatedAt());
  }

  /**
   * Test adding duplicate permission does not add twice.
   */
  #[Test]
  public function testAddDuplicatePermissionDoesNotAddTwice(): void
  {
    $role = $this->createTestRole();
    $permission = $this->createTestPermission('users.create');

    $role->addPermission($permission);
    $role->addPermission($permission);

    $this->assertCount(1, $role->permissions());
  }

  /**
   * Test removing a permission from a role.
   */
  #[Test]
  public function testRemovePermissionFromRole(): void
  {
    $role = $this->createTestRole();
    $permission = $this->createTestPermission('users.create');

    $role->addPermission($permission);
    $this->assertCount(1, $role->permissions());

    $role->removePermission($permission);

    $this->assertCount(0, $role->permissions());
  }

  /**
   * Test hasPermission with exact match.
   */
  #[Test]
  public function testHasPermissionWithExactMatch(): void
  {
    $role = $this->createTestRole();
    $permission = $this->createTestPermission('users.create');
    $checkPermission = $this->createTestPermission('users.create');

    $role->addPermission($permission);

    $this->assertTrue($role->hasPermission($checkPermission));
  }

  /**
   * Test hasPermission with wildcard match.
   */
  #[Test]
  public function testHasPermissionWithWildcardMatch(): void
  {
    $role = $this->createTestRole();
    $wildcardPermission = $this->createTestPermission('users.*');
    $specificPermission = $this->createTestPermission('users.create');

    $role->addPermission($wildcardPermission);

    $this->assertTrue($role->hasPermission($specificPermission));
  }

  /**
   * Test hasPermission returns false for missing permission.
   */
  #[Test]
  public function testHasPermissionReturnsFalseForMissingPermission(): void
  {
    $role = $this->createTestRole();
    $permission = $this->createTestPermission('users.create');
    $otherPermission = $this->createTestPermission('clients.read');

    $role->addPermission($permission);

    $this->assertFalse($role->hasPermission($otherPermission));
  }

  // #endregion

  // #region Update Tests

  /**
   * Test updating role name and description.
   */
  #[Test]
  public function testUpdateRoleNameAndDescription(): void
  {
    $role = $this->createTestRole();
    $newName = new RoleName('moderator');
    $newDescription = 'Moderator role';

    $role->update($newName, $newDescription);

    $this->assertEquals('moderator', $role->name()->value);
    $this->assertEquals('Moderator role', $role->description());
    $this->assertNotNull($role->updatedAt());
  }

  // #endregion

  // #region Equality Tests

  /**
   * Test role equality by ID.
   */
  #[Test]
  public function testRoleEqualityById(): void
  {
    $id = new RoleId('550e8400-e29b-41d4-a716-446655440000');

    $role1 = Role::create(
      id: $id,
      name: new RoleName('admin'),
      description: 'Admin 1',
    );

    $role2 = Role::create(
      id: $id,
      name: new RoleName('admin'),
      description: 'Admin 2',
    );

    $this->assertTrue($role1->equals($role2));
  }

  /**
   * Test role inequality with different IDs.
   */
  #[Test]
  public function testRoleInequalityWithDifferentIds(): void
  {
    $role1 = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440000'),
      name: new RoleName('admin'),
    );

    $role2 = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440001'),
      name: new RoleName('admin'),
    );

    $this->assertFalse($role1->equals($role2));
  }

  // #endregion

  // #region Helper Methods

  private function createTestRole(): Role
  {
    return Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440000'),
      name: new RoleName('testrole'),
      description: 'Test role',
    );
  }

  private function createTestPermission(string $name): Permission
  {
    return Permission::create(
      id: new PermissionId('660e8400-e29b-41d4-a716-' . substr(md5($name), 0, 12)),
      name: new PermissionName($name),
      description: 'Test permission',
    );
  }

  // #endregion
}
