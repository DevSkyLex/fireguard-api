<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Role\UpdateRole;

use Authorization\Application\Port\Outbound\{PermissionRepositoryPort, RoleRepositoryPort};
use Authorization\Application\UseCase\Command\Role\UpdateRole\{UpdateRoleCommand, UpdateRoleHandler, UpdateRoleResult};
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test UpdateRoleHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateRoleHandler::class)]
final class UpdateRoleHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeThrowsWhenRoleMissing(): void
  {
    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $permissionRepository = $this->createStub(PermissionRepositoryPort::class);

    $handler = new UpdateRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $command = new UpdateRoleCommand(
      roleId: '550e8400-e29b-41d4-a716-446655440000',
      name: '',
      description: null,
      permissionIds: [],
    );

    $this->expectException(RoleNotFoundException::class);

    $handler->__invoke($command);
  }

  #[Test]
  public function testInvokeUpdatesRoleAndPermissions(): void
  {
    $roleId = new RoleId('550e8400-e29b-41d4-a716-446655440010');

    $existingPermission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440020'),
      name: new PermissionName('users.read'),
      description: 'Read users',
    );

    $role = Role::create(
      id: $roleId,
      name: new RoleName('admin'),
      description: 'Admin role',
    );
    $role->addPermission($existingPermission);

    $newPermission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440021'),
      name: new PermissionName('users.create'),
      description: 'Create users',
    );

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(fn (RoleId $id) => $id->value === $roleId->value))
      ->willReturn($role);
    $roleRepository->expects(self::once())
      ->method('save')
      ->with($role);

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(fn (PermissionId $id) => $id->value === $newPermission->id()->value))
      ->willReturn($newPermission);

    $handler = new UpdateRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $command = new UpdateRoleCommand(
      roleId: $roleId->value,
      name: '',
      description: null,
      permissionIds: [$newPermission->id()->value],
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(UpdateRoleResult::class, $result);
    self::assertSame($roleId->value, $result->id);
    self::assertSame('admin', $result->name);
    self::assertSame('Admin role', $result->description);
    self::assertCount(1, $result->permissions);
    self::assertSame('users.create', $result->permissions[0]->name);
  }
  // #endregion
}
