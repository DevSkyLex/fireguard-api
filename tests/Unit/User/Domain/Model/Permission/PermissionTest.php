<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Model\Permission;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Domain\Model\Permission\Permission;
use User\Domain\ValueObject\{PermissionId, PermissionName};

/**
 * Test PermissionTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Permission::class)]
final class PermissionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testMatchesAndEquals(): void
  {
    $permissionId = new PermissionId('550e8400-e29b-41d4-a716-446655440040');
    $permission = new Permission(
      id: $permissionId,
      name: new PermissionName('users.create'),
      description: 'Create users',
    );

    self::assertSame($permissionId, $permission->id());
    self::assertSame('Create users', $permission->description());
    self::assertTrue($permission->matches(new PermissionName('users.create')));

    $same = new Permission(
      id: $permissionId,
      name: new PermissionName('users.create'),
      description: 'Create users',
    );

    self::assertTrue($permission->equals($same));
  }
  // #endregion
}
