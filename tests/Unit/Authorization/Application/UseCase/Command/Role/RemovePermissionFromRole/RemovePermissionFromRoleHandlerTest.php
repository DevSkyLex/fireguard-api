<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole;

use Authorization\Application\Port\Outbound\{PermissionRepositoryPort, RoleRepositoryPort};
use Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole\{RemovePermissionFromRoleCommand, RemovePermissionFromRoleHandler, RemovePermissionFromRoleResult};
use Authorization\Domain\Exception\{PermissionNotFoundException, RoleNotFoundException};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test RemovePermissionFromRoleHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RemovePermissionFromRoleHandler::class)]
final class RemovePermissionFromRoleHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeRemovesPermission(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440200'),
      name: new PermissionName('posts.delete'),
      description: 'Delete posts',
    );

    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440201'),
      name: new RoleName('moderator'),
      description: 'Moderator role',
    );
    $role->addPermission($permission);

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

    $handler = new RemovePermissionFromRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $result = $handler->__invoke(new RemovePermissionFromRoleCommand(
      roleId: $role->id()->value,
      permissionId: $permission->id()->value,
    ));

    self::assertInstanceOf(RemovePermissionFromRoleResult::class, $result);
    self::assertCount(0, $result->permissions);
  }

  #[Test]
  public function testInvokeThrowsWhenRoleMissing(): void
  {
    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $permissionRepository = $this->createStub(PermissionRepositoryPort::class);

    $handler = new RemovePermissionFromRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $this->expectException(RoleNotFoundException::class);

    $handler->__invoke(new RemovePermissionFromRoleCommand(
      roleId: '550e8400-e29b-41d4-a716-446655440202',
      permissionId: '550e8400-e29b-41d4-a716-446655440203',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionMissing(): void
  {
    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440204'),
      name: new RoleName('moderator'),
      description: 'Moderator role',
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

    $handler = new RemovePermissionFromRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $this->expectException(PermissionNotFoundException::class);

    $handler->__invoke(new RemovePermissionFromRoleCommand(
      roleId: $role->id()->value,
      permissionId: '550e8400-e29b-41d4-a716-446655440205',
    ));
  }

  #[Test]
  public function testInvokeMapsRemainingPermissions(): void
  {
    $permissionToRemove = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440210'),
      name: new PermissionName('posts.delete'),
      description: 'Delete posts',
    );
    $permissionToKeep = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440211'),
      name: new PermissionName('posts.read'),
      description: 'Read posts',
    );

    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440212'),
      name: new RoleName('moderator'),
      description: 'Moderator role',
    );
    $role->addPermission($permissionToRemove);
    $role->addPermission($permissionToKeep);

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
      ->willReturn($permissionToRemove);

    $handler = new RemovePermissionFromRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
    );

    $result = $handler->__invoke(new RemovePermissionFromRoleCommand(
      roleId: $role->id()->value,
      permissionId: $permissionToRemove->id()->value,
    ));

    self::assertCount(1, $result->permissions);
    self::assertSame($permissionToKeep->id()->value, $result->permissions[0]->id);
    self::assertSame($permissionToKeep->name()->value, $result->permissions[0]->name);
  }
  // #endregion
}
