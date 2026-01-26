<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Query\Role\ListRoles;

use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Application\UseCase\Query\Role\ListRoles\{ListRolesHandler, ListRolesQuery, ListRolesResult};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ListRolesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListRolesHandler::class)]
final class ListRolesHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsMappedRoles(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440900'),
      name: new PermissionName('users.read'),
      description: 'Read users',
    );

    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440901'),
      name: new RoleName('reader'),
      description: 'Reader role',
    );
    $role->addPermission($permission);

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findAll')
      ->willReturn([$role]);

    $handler = new ListRolesHandler($roleRepository);

    $result = $handler->__invoke(new ListRolesQuery());

    self::assertInstanceOf(ListRolesResult::class, $result);
    self::assertCount(1, $result->roles);
    self::assertSame('reader', $result->roles[0]->name);
    self::assertCount(1, $result->roles[0]->permissions);
  }
  // #endregion
}
