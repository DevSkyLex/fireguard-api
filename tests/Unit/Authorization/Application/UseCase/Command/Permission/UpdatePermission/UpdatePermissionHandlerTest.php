<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Permission\UpdatePermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\UseCase\Command\Permission\UpdatePermission\{UpdatePermissionCommand, UpdatePermissionHandler, UpdatePermissionResult};
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test UpdatePermissionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdatePermissionHandler::class)]
final class UpdatePermissionHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeThrowsWhenPermissionMissing(): void
  {
    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new UpdatePermissionHandler($permissionRepository);

    $this->expectException(PermissionNotFoundException::class);

    $handler->__invoke(new UpdatePermissionCommand(
      permissionId: '550e8400-e29b-41d4-a716-446655440400',
    ));
  }

  #[Test]
  public function testInvokeReturnsPermissionResult(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440401'),
      name: new PermissionName('roles.assign'),
      description: 'Assign roles',
    );

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findById')
      ->willReturn($permission);

    $handler = new UpdatePermissionHandler($permissionRepository);

    $result = $handler->__invoke(new UpdatePermissionCommand(
      permissionId: $permission->id()->value,
    ));

    self::assertInstanceOf(UpdatePermissionResult::class, $result);
    self::assertSame($permission->id()->value, $result->id);
    self::assertSame('roles.assign', $result->name);
  }
  // #endregion
}
