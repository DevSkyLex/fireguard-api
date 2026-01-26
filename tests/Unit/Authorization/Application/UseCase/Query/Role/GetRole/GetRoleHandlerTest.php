<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Query\Role\GetRole;

use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Application\UseCase\Query\Role\GetRole\{GetRoleHandler, GetRoleQuery, GetRoleResult};
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId, RoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test GetRoleHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetRoleHandler::class)]
final class GetRoleHandlerTest extends TestCase
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

    $handler = new GetRoleHandler($roleRepository);

    $this->expectException(RoleNotFoundException::class);

    $handler->__invoke(new GetRoleQuery(roleId: '550e8400-e29b-41d4-a716-446655440800'));
  }

  #[Test]
  public function testInvokeReturnsRoleResult(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440801'),
      name: new PermissionName('users.read'),
      description: 'Read users',
    );

    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440802'),
      name: new RoleName('reader'),
      description: 'Reader role',
    );
    $role->addPermission($permission);

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn($role);

    $handler = new GetRoleHandler($roleRepository);

    $result = $handler->__invoke(new GetRoleQuery(roleId: $role->id()->value));

    self::assertInstanceOf(GetRoleResult::class, $result);
    self::assertSame($role->id()->value, $result->id);
    self::assertSame('reader', $result->name);
    self::assertCount(1, $result->permissions);
  }
  // #endregion
}
