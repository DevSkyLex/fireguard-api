<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Permission\CreatePermission;

use Authorization\Application\Port\Outbound\Internal\PermissionRepositoryPort;
use Authorization\Application\Port\Outbound\PermissionCatalogPort;
use Authorization\Application\UseCase\Command\Permission\CreatePermission\{CreatePermissionCommand, CreatePermissionHandler, CreatePermissionResult};
use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Exception\InvalidValueException;

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

    /** @var UuidGeneratorPort&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidGeneratorPort::class);
    $uuidFactory->expects(self::once())
      ->method('generate')
      ->willReturn((string) $permissionId);

    $handler = new CreatePermissionHandler(
      permissionRepository: $permissionRepository,
      uuidFactory: $uuidFactory,
      permissionCatalog: $this->catalog('roles.assign', true),
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

  #[Test]
  public function testUnknownPermissionCannotBeCreatedEvenWhenSyntacticallyValid(): void
  {
    $repository = $this->createMock(PermissionRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $uuidFactory = $this->createMock(UuidGeneratorPort::class);
    $uuidFactory->expects(self::never())->method('generate');
    $handler = new CreatePermissionHandler($repository, $uuidFactory, $this->catalog('removed.read', false));

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Unknown permission catalog key: removed.read');
    $handler(new CreatePermissionCommand('removed.read', 'Historical key'));
  }

  private function catalog(string $name, bool $registered): PermissionCatalogPort
  {
    $catalog = $this->createMock(PermissionCatalogPort::class);
    $catalog->expects(self::once())->method('contains')->with($name)->willReturn($registered);

    return $catalog;
  }
  // #endregion
}
