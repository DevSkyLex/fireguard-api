<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor\Role;

use ApiPlatform\Metadata\Post;
use Authorization\Application\UseCase\Command\Role\CreateRole\{CreateRoleCommand, CreateRoleResult};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Presentation\Api\Dto\Input\Role\RoleInput;
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Authorization\Presentation\Api\Processor\Role\CreateRoleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test CreateRoleProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateRoleProcessor::class)]
final class CreateRoleProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private CreateRoleProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new CreateRoleProcessor($this->commandBus);
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessCreatesRole(): void
  {
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $result = new CreateRoleResult(
      id: $roleId,
      name: 'admin',
      description: 'Admin role',
      isSystem: false,
      createdAt: '2025-01-01 12:00:00',
      permissions: [
        new GetPermissionResult(
          id: $permissionId,
          name: 'users.create',
          description: 'Create users',
          createdAt: '2025-01-01 12:00:00',
        ),
      ],
    );

    $input = new RoleInput();
    $input->name = 'admin';
    $input->description = 'Admin role';
    $input->isSystem = false;
    $input->permissionIds = ['perm' => $permissionId];

    $this->commandBus->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (CreateRoleCommand $command) => 'admin' === $command->name
          && 'Admin role' === $command->description
          && false === $command->isSystem
          && $command->permissionIds === [$permissionId],
      ))
      ->willReturn($result);

    $output = $this->processor->process($input, new Post());

    $this->assertInstanceOf(RoleOutput::class, $output);
    $this->assertSame($roleId, $output->id);
    $this->assertCount(1, $output->permissions);
    $this->assertSame($permissionId, $output->permissions[0]->id);
  }
  // #endregion
}
