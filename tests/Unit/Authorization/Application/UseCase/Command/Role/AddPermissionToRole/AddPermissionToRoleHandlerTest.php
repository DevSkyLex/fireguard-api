<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Role\AddPermissionToRole;

use Authorization\Application\Port\Outbound\{PermissionRepositoryPort, RoleRepositoryPort};
use Authorization\Application\UseCase\Command\Role\AddPermissionToRole\{AddPermissionToRoleCommand, AddPermissionToRoleHandler, AddPermissionToRoleResult};
use Authorization\Domain\Exception\{PermissionNotFoundException, RoleNotFoundException};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test AddPermissionToRoleHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddPermissionToRoleHandler::class)]
final class AddPermissionToRoleHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeAddsPermission(): void
  {
    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440100'),
      name: new RoleName('editor'),
      description: 'Editor role',
    );

    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440101'),
      name: new PermissionName('posts.edit'),
      description: 'Edit posts',
    );

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn($role);
    $roleRepository->expects(self::once())
      ->method('save')
      ->with($role);

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findById')
      ->willReturn($permission);

    $handler = new AddPermissionToRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $result = $handler->__invoke(new AddPermissionToRoleCommand(
      roleId: $role->id()->value,
      permissionId: $permission->id()->value,
    ));

    self::assertInstanceOf(AddPermissionToRoleResult::class, $result);
    self::assertCount(1, $result->permissions);
    self::assertSame('posts.edit', $result->permissions[0]->name);
  }

  #[Test]
  public function testInvokeThrowsWhenRoleMissing(): void
  {
    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);

    $handler = new AddPermissionToRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $this->expectException(RoleNotFoundException::class);

    $handler->__invoke(new AddPermissionToRoleCommand(
      roleId: '550e8400-e29b-41d4-a716-446655440102',
      permissionId: '550e8400-e29b-41d4-a716-446655440103',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionMissing(): void
  {
    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440104'),
      name: new RoleName('editor'),
      description: 'Editor role',
    );

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn($role);

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new AddPermissionToRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $this->expectException(PermissionNotFoundException::class);

    $handler->__invoke(new AddPermissionToRoleCommand(
      roleId: $role->id()->value,
      permissionId: '550e8400-e29b-41d4-a716-446655440105',
    ));
  }
  // #endregion
}
