<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Permission\DeletePermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\UseCase\Command\Permission\DeletePermission\{DeletePermissionCommand, DeletePermissionHandler, DeletePermissionResult};
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test DeletePermissionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeletePermissionHandler::class)]
final class DeletePermissionHandlerTest extends TestCase
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

    $handler = new DeletePermissionHandler($permissionRepository);

    $this->expectException(PermissionNotFoundException::class);

    $handler->__invoke(new DeletePermissionCommand(permissionId: '550e8400-e29b-41d4-a716-446655440500'));
  }

  #[Test]
  public function testInvokeDeletesPermission(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440501'),
      name: new PermissionName('users.delete'),
      description: 'Delete users',
    );

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findById')
      ->willReturn($permission);
    $permissionRepository->expects(self::once())
      ->method('delete')
      ->with($permission);

    $handler = new DeletePermissionHandler($permissionRepository);

    $result = $handler->__invoke(new DeletePermissionCommand(permissionId: $permission->id()->value));

    self::assertInstanceOf(DeletePermissionResult::class, $result);
    self::assertSame($permission->id()->value, $result->permissionId);
  }
  // #endregion
}
