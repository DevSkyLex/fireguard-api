<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Query\Permission\ListPermissions;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\UseCase\Query\Permission\ListPermissions\{ListPermissionsHandler, ListPermissionsQuery, ListPermissionsResult};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ListPermissionsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListPermissionsHandler::class)]
final class ListPermissionsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsMappedPermissions(): void
  {
    $permissions = [
      Permission::create(
        id: new PermissionId('550e8400-e29b-41d4-a716-446655440700'),
        name: new PermissionName('users.read'),
        description: 'Read users',
      ),
      Permission::create(
        id: new PermissionId('550e8400-e29b-41d4-a716-446655440701'),
        name: new PermissionName('users.create'),
        description: 'Create users',
      ),
    ];

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findAll')
      ->willReturn($permissions);

    $handler = new ListPermissionsHandler($permissionRepository);

    $result = $handler->__invoke(new ListPermissionsQuery());

    self::assertInstanceOf(ListPermissionsResult::class, $result);
    self::assertCount(2, $result->permissions);
    self::assertSame('users.read', $result->permissions[0]->name);
    self::assertSame('users.create', $result->permissions[1]->name);
  }
  // #endregion
}
