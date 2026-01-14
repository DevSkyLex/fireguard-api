<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Authorization\Application\UseCase\Command\Role\AddPermissionToRole\{AddPermissionToRoleCommand, AddPermissionToRoleResult};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Exception\{PermissionNotFoundException, RoleNotFoundException};
use Authorization\Presentation\Api\Dto\Input\Role\AddPermissionInput;
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Authorization\Presentation\Api\Processor\Role\AddPermissionToRoleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test AddPermissionToRoleProcessorTest.
 *
 * @category Processor Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddPermissionToRoleProcessor::class)]
final class AddPermissionToRoleProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private AddPermissionToRoleProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new AddPermissionToRoleProcessor($this->commandBus);
  }
  // #endregion

  // #region Tests

  /**
   * Test successfully adding a permission to a role.
   */
  #[Test]
  public function testAddPermissionToRoleSuccessfully(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';
    $createdAt = '2025-01-01 12:00:00';

    $result = new AddPermissionToRoleResult(
      id: $roleId,
      name: 'admin',
      description: 'Admin role',
      isSystem: false,
      createdAt: $createdAt,
      permissions: [
        new GetPermissionResult(
          id: $permissionId,
          name: 'users.create',
          description: 'Create users',
          createdAt: $createdAt,
        ),
      ],
    );

    $input = new AddPermissionInput();
    $input->permissionId = $permissionId;

    $this->commandBus
      ->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (AddPermissionToRoleCommand $command) => $command->roleId === $roleId
          && $command->permissionId === $permissionId,
      ))
      ->willReturn($result);

    $operation = new Post();

    // Act
    $output = $this->processor->process(
      $input,
      $operation,
      ['roleId' => $roleId],
    );

    // Assert
    $this->assertInstanceOf(RoleOutput::class, $output);
    $this->assertSame($roleId, $output->id);
    $this->assertSame('admin', $output->name);
    $this->assertCount(1, $output->permissions);
    $this->assertSame($permissionId, $output->permissions[0]->id);
  }

  /**
   * Test adding permission to non-existent role throws exception.
   */
  #[Test]
  public function testAddPermissionToNonExistentRoleThrowsException(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $input = new AddPermissionInput();
    $input->permissionId = $permissionId;

    $this->commandBus
      ->expects($this->once())
      ->method('dispatch')
      ->willThrowException(RoleNotFoundException::withId(roleId: $roleId));

    $operation = new Post();

    // Assert
    $this->expectException(RoleNotFoundException::class);

    // Act
    $this->processor->process(
      $input,
      $operation,
      ['roleId' => $roleId],
    );
  }

  /**
   * Test adding non-existent permission throws exception.
   */
  #[Test]
  public function testAddNonExistentPermissionThrowsException(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $input = new AddPermissionInput();
    $input->permissionId = $permissionId;

    $this->commandBus
      ->expects($this->once())
      ->method('dispatch')
      ->willThrowException(PermissionNotFoundException::withId(permissionId: $permissionId));

    $operation = new Post();

    // Assert
    $this->expectException(PermissionNotFoundException::class);

    // Act
    $this->processor->process(
      $input,
      $operation,
      ['roleId' => $roleId],
    );
  }

  // #endregion
}
