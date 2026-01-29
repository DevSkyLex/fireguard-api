<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Permission\CreatePermission;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\UseCase\Command\Permission\CreatePermission\{CreatePermissionCommand, CreatePermissionHandler, CreatePermissionResult};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

/**
 * Test CreatePermissionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreatePermissionHandler::class)]
final class CreatePermissionHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeCreatesPermission(): void
  {
    $permissionId = new PermissionId('550e8400-e29b-41d4-a716-446655440300');

    /** @var PermissionRepositoryPort&MockObject $permissionRepository */
    $permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $permissionRepository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Permission::class));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(PermissionId::class)
      ->willReturn($permissionId);

    $handler = new CreatePermissionHandler(
      permissionRepository: $permissionRepository,
      uuidFactory: $uuidFactory,
    );

    $command = new CreatePermissionCommand(
      name: 'roles.assign',
      description: 'Assign roles',
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(CreatePermissionResult::class, $result);
    self::assertSame($permissionId->value, $result->id);
    self::assertSame('roles.assign', $result->name);
    self::assertSame('Assign roles', $result->description);
  }
  // #endregion
}
