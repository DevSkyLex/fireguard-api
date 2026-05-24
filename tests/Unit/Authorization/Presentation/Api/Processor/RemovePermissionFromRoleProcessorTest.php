<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Delete;
use Authorization\Application\UseCase\Command\Role\RemovePermissionFromRole\{RemovePermissionFromRoleCommand, RemovePermissionFromRoleResult};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Exception\{PermissionNotFoundException, RoleNotFoundException};
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Authorization\Presentation\Api\Processor\Role\RemovePermissionFromRoleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test RemovePermissionFromRoleProcessorTest.
 *
 * @category Processor Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RemovePermissionFromRoleProcessor::class)]
final class RemovePermissionFromRoleProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private RemovePermissionFromRoleProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new RemovePermissionFromRoleProcessor($this->commandBus);
  }
  // #endregion

  // #region Tests

  /**
   * Test successfully removing a permission from a role.
   */
  #[Test]
  public function testRemovePermissionFromRoleSuccessfully(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';
    $createdAt = '2025-01-01 12:00:00';

    $result = new RemovePermissionFromRoleResult(
      id: $roleId,
      name: 'admin',
      description: 'Admin role',
      isSystem: false,
      createdAt: $createdAt,
      permissions: [],
    );

    $this->commandBus
      ->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (RemovePermissionFromRoleCommand $command) => $command->roleId === $roleId
          && $command->permissionId === $permissionId,
      ))
      ->willReturn($result);

    $operation = new Delete();

    // Act
    $output = $this->processor->process(
      null,
      $operation,
      ['roleId' => $roleId, 'permissionId' => $permissionId],
    );

    // Assert
    $this->assertInstanceOf(RoleOutput::class, $output);
    $this->assertSame($roleId, $output->id);
    $this->assertEmpty($output->permissions);
  }

  /**
   * Test removing permission from non-existent role throws exception.
   */
  #[Test]
  public function testRemovePermissionFromNonExistentRoleThrowsException(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $this->commandBus
      ->expects($this->once())
      ->method('dispatch')
      ->willThrowException(RoleNotFoundException::withId(roleId: $roleId));

    $operation = new Delete();

    // Assert
    $this->expectException(RoleNotFoundException::class);

    // Act
    $this->processor->process(
      null,
      $operation,
      ['roleId' => $roleId, 'permissionId' => $permissionId],
    );
  }

  /**
   * Test removing non-existent permission throws exception.
   */
  #[Test]
  public function testRemoveNonExistentPermissionThrowsException(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $this->commandBus
      ->expects($this->once())
      ->method('dispatch')
      ->willThrowException(PermissionNotFoundException::withId(permissionId: $permissionId));

    $operation = new Delete();

    // Assert
    $this->expectException(PermissionNotFoundException::class);

    // Act
    $this->processor->process(
      null,
      $operation,
      ['roleId' => $roleId, 'permissionId' => $permissionId],
    );
  }

  #[Test]
  public function testProcessThrowsWhenRoleIdIsInvalid(): void
  {
    $operation = new Delete();

    $this->commandBus->expects($this->never())->method(self::anything());

    $this->expectException(RoleNotFoundException::class);

    $this->processor->process(
      null,
      $operation,
      ['roleId' => ['bad'], 'permissionId' => 'perm-1'],
    );
  }

  #[Test]
  public function testProcessThrowsWhenPermissionIdIsInvalid(): void
  {
    $operation = new Delete();

    $this->commandBus->expects($this->never())->method(self::anything());

    $this->expectException(PermissionNotFoundException::class);

    $this->processor->process(
      null,
      $operation,
      ['roleId' => 'role-1', 'permissionId' => ['bad']],
    );
  }

  #[Test]
  public function testProcessMapsPermissionOutputs(): void
  {
    $roleId = '550e8400-e29b-41d4-a716-446655440010';
    $permissionId = '660e8400-e29b-41d4-a716-446655440010';

    $result = new RemovePermissionFromRoleResult(
      id: $roleId,
      name: 'admin',
      description: 'Admin role',
      isSystem: true,
      createdAt: '2025-01-02 09:30:00',
      permissions: [
        new GetPermissionResult(
          id: $permissionId,
          name: 'users.read',
          description: 'Read users',
          createdAt: '2025-01-02 08:00:00',
        ),
      ],
    );

    $this->commandBus
      ->expects($this->once())
      ->method('dispatch')
      ->willReturn($result);

    $output = $this->processor->process(
      null,
      new Delete(),
      ['roleId' => $roleId, 'permissionId' => $permissionId],
    );

    $this->assertCount(1, $output->permissions);
    $this->assertSame($permissionId, $output->permissions[0]->id);
    $this->assertSame('users.read', $output->permissions[0]->name);
  }

  // #endregion
}
