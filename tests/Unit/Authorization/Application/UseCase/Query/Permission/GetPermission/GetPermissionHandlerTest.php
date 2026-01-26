<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Query\Permission\GetPermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\UseCase\Query\Permission\GetPermission\{GetPermissionHandler, GetPermissionQuery, GetPermissionResult};
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test GetPermissionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPermissionHandler::class)]
final class GetPermissionHandlerTest extends TestCase
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

    $handler = new GetPermissionHandler($permissionRepository);

    $this->expectException(PermissionNotFoundException::class);

    $handler->__invoke(new GetPermissionQuery(permissionId: '550e8400-e29b-41d4-a716-446655440600'));
  }

  #[Test]
  public function testInvokeReturnsResult(): void
  {
    $permission = Permission::create(
      id: new PermissionId('550e8400-e29b-41d4-a716-446655440601'),
      name: new PermissionName('roles.read'),
      description: 'Read roles',
    );

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('findById')
      ->willReturn($permission);

    $handler = new GetPermissionHandler($permissionRepository);

    $result = $handler->__invoke(new GetPermissionQuery(permissionId: $permission->id()->value));

    self::assertInstanceOf(GetPermissionResult::class, $result);
    self::assertSame($permission->id()->value, $result->id);
    self::assertSame('roles.read', $result->name);
  }
  // #endregion
}
