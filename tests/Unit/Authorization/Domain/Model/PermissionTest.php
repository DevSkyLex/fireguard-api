<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Model;

use Authorization\Domain\Model\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test PermissionTest.
 *
 * @category Domain Model Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Permission::class)]
final class PermissionTest extends TestCase
{
  // #region Factory Method Tests

  /**
   * Test permission creation with valid data.
   */
  #[Test]
  public function testCreatePermissionWithValidData(): void
  {
    $id = new PermissionId('550e8400-e29b-41d4-a716-446655440000');
    $name = new PermissionName('users.create');
    $description = 'Create new users';

    $permission = Permission::create(
      id: $id,
      name: $name,
      description: $description,
    );

    $this->assertEquals($id->value, $permission->id()->value);
    $this->assertEquals('users.create', $permission->name()->value);
    $this->assertEquals('Create new users', $permission->description());
    $this->assertInstanceOf(DateTimeImmutable::class, $permission->createdAt());
  }

  /**
   * Test permission creation with empty description.
   */
  #[Test]
  public function testCreatePermissionWithEmptyDescription(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440001'),
      name: new PermissionName('users.read'),
    );

    $this->assertEquals('', $permission->description());
  }

  // #endregion

  // #region Matching Tests

  /**
   * Test matches with exact permission name.
   */
  #[Test]
  public function testMatchesWithExactName(): void
  {
    $permission = $this->createPermission('users.create');
    $required = new PermissionName('users.create');

    $this->assertTrue($permission->matches($required));
  }

  /**
   * Test matches with wildcard permission.
   */
  #[Test]
  public function testMatchesWithWildcard(): void
  {
    $permission = $this->createPermission('users.*');
    $required = new PermissionName('users.create');

    $this->assertTrue($permission->matches($required));
  }

  /**
   * Test matches with super wildcard.
   */
  #[Test]
  public function testMatchesWithSuperWildcard(): void
  {
    $permission = $this->createPermission('*.*');
    $required = new PermissionName('users.create');

    $this->assertTrue($permission->matches($required));
  }

  /**
   * Test does not match different permission.
   */
  #[Test]
  public function testDoesNotMatchDifferentPermission(): void
  {
    $permission = $this->createPermission('users.create');
    $required = new PermissionName('clients.read');

    $this->assertFalse($permission->matches($required));
  }

  /**
   * Test wildcard does not match different resource.
   */
  #[Test]
  public function testWildcardDoesNotMatchDifferentResource(): void
  {
    $permission = $this->createPermission('users.*');
    $required = new PermissionName('clients.read');

    $this->assertFalse($permission->matches($required));
  }

  // #endregion

  // #region Equality Tests

  /**
   * Test permission equality by ID.
   */
  #[Test]
  public function testPermissionEqualityById(): void
  {
    $id = new PermissionId('550e8400-e29b-41d4-a716-446655440000');

    $permission1 = Permission::create(
      id: $id,
      name: new PermissionName('users.create'),
      description: 'Create',
    );

    $permission2 = Permission::create(
      id: $id,
      name: new PermissionName('users.create'),
      description: 'Create users',
    );

    $this->assertTrue($permission1->equals($permission2));
  }

  /**
   * Test permission inequality with different IDs.
   */
  #[Test]
  public function testPermissionInequalityWithDifferentIds(): void
  {
    $permission1 = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440000'),
      name: new PermissionName('users.create'),
    );

    $permission2 = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440001'),
      name: new PermissionName('users.create'),
    );

    $this->assertFalse($permission1->equals($permission2));
  }

  // #endregion

  // #region Helper Methods

  private function createPermission(string $name): Permission
  {
    return Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440000'),
      name: new PermissionName($name),
      description: 'Test permission',
    );
  }

  // #endregion
}
