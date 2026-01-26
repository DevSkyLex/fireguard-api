<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Role\CreateRole;

use Authorization\Application\Port\Outbound\{PermissionRepositoryPort, RoleRepositoryPort};
use Authorization\Application\UseCase\Command\Role\CreateRole\{CreateRoleCommand, CreateRoleHandler, CreateRoleResult};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName, RoleId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

use function in_array;

/**
 * Test CreateRoleHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateRoleHandler::class)]
final class CreateRoleHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeCreatesRoleAndMapsPermissions(): void
  {
    $roleId = new RoleId('550e8400-e29b-41d4-a716-446655440000');

    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440010'),
      name: new PermissionName('users.create'),
      description: 'Create users',
    );

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(\Authorization\Domain\Model\Role\Role::class));

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::exactly(2))
      ->method('findById')
      ->with(self::callback(
        fn (PermissionId $id) => in_array($id->value, [
          '550e8400-e29b-41d4-a716-446655440010',
          '550e8400-e29b-41d4-a716-446655440011',
        ], true),
      ))
      ->willReturnOnConsecutiveCalls($permission, null);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(RoleId::class)
      ->willReturn($roleId);

    $handler = new CreateRoleHandler(
      roleRepository: $roleRepository,
      permissionRepository: $permissionRepository,
      uuidFactory: $uuidFactory,
    );

    $command = new CreateRoleCommand(
      name: 'admin',
      description: 'Admin role',
      isSystem: false,
      permissionIds: [
        '550e8400-e29b-41d4-a716-446655440010',
        '550e8400-e29b-41d4-a716-446655440011',
      ],
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(CreateRoleResult::class, $result);
    self::assertSame($roleId->value, $result->id);
    self::assertSame('admin', $result->name);
    self::assertSame('Admin role', $result->description);
    self::assertFalse($result->isSystem);
    self::assertCount(1, $result->permissions);
    self::assertSame('users.create', $result->permissions[0]->name);
  }
  // #endregion
}
