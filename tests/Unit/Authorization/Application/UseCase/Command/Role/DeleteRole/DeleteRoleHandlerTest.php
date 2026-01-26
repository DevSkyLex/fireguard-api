<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Application\UseCase\Command\Role\DeleteRole;

use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Application\UseCase\Command\Role\DeleteRole\{DeleteRoleCommand, DeleteRoleHandler, DeleteRoleResult};
use Authorization\Domain\Exception\{RoleNotFoundException, SystemRoleDeletionException};
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{RoleId, RoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test DeleteRoleHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteRoleHandler::class)]
final class DeleteRoleHandlerTest extends TestCase
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

    $handler = new DeleteRoleHandler($roleRepository);

    $this->expectException(RoleNotFoundException::class);

    $handler->__invoke(new DeleteRoleCommand(roleId: '550e8400-e29b-41d4-a716-446655440000'));
  }

  #[Test]
  public function testInvokeThrowsWhenRoleIsSystem(): void
  {
    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440001'),
      name: new RoleName('system'),
      description: 'System role',
      isSystem: true,
    );

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn($role);

    $handler = new DeleteRoleHandler($roleRepository);

    $this->expectException(SystemRoleDeletionException::class);

    $handler->__invoke(new DeleteRoleCommand(roleId: $role->id()->value));
  }

  #[Test]
  public function testInvokeDeletesRole(): void
  {
    $role = Role::create(
      id: new RoleId('550e8400-e29b-41d4-a716-446655440002'),
      name: new RoleName('member'),
      description: 'Member role',
    );

    /** @var RoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(RoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findById')
      ->willReturn($role);
    $roleRepository->expects(self::once())
      ->method('delete')
      ->with($role);

    $handler = new DeleteRoleHandler($roleRepository);

    $result = $handler->__invoke(new DeleteRoleCommand(roleId: $role->id()->value));

    self::assertInstanceOf(DeleteRoleResult::class, $result);
    self::assertSame($role->id()->value, $result->roleId);
  }
  // #endregion
}
