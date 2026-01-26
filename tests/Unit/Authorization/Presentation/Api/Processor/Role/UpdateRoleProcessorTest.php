<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor\Role;

use ApiPlatform\Metadata\Put;
use Authorization\Application\UseCase\Command\Role\UpdateRole\{UpdateRoleCommand, UpdateRoleResult};
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Presentation\Api\Dto\Input\Role\RoleInput;
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Authorization\Presentation\Api\Processor\Role\UpdateRoleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test UpdateRoleProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateRoleProcessor::class)]
final class UpdateRoleProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private UpdateRoleProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new UpdateRoleProcessor($this->commandBus);
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdMissing(): void
  {
    $input = new RoleInput();

    $this->expectException(RoleNotFoundException::class);

    $this->processor->process($input, new Put(), ['id' => null]);
  }

  #[Test]
  public function testProcessUpdatesRole(): void
  {
    $roleId = '550e8400-e29b-41d4-a716-446655440010';
    $permissionId = '660e8400-e29b-41d4-a716-446655440010';

    $result = new UpdateRoleResult(
      id: $roleId,
      name: 'editor',
      description: 'Editor role',
      isSystem: false,
      createdAt: '2025-01-02 12:00:00',
      permissions: [
        new GetPermissionResult(
          id: $permissionId,
          name: 'posts.edit',
          description: 'Edit posts',
          createdAt: '2025-01-02 12:00:00',
        ),
      ],
    );

    $input = new RoleInput();
    $input->name = 'editor';
    $input->description = 'Editor role';
    $input->permissionIds = [$permissionId];

    $this->commandBus->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (UpdateRoleCommand $command) => $command->roleId === $roleId
          && 'editor' === $command->name
          && 'Editor role' === $command->description
          && $command->permissionIds === [$permissionId],
      ))
      ->willReturn($result);

    $output = $this->processor->process($input, new Put(), ['id' => $roleId]);

    $this->assertInstanceOf(RoleOutput::class, $output);
    $this->assertSame($roleId, $output->id);
    $this->assertSame('editor', $output->name);
    $this->assertCount(1, $output->permissions);
  }
  // #endregion
}
