<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor\Permission;

use ApiPlatform\Metadata\Post;
use Authorization\Application\UseCase\Command\Permission\CreatePermission\{CreatePermissionCommand, CreatePermissionResult};
use Authorization\Presentation\Api\Dto\Input\Permission\PermissionInput;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Authorization\Presentation\Api\Processor\Permission\CreatePermissionProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test CreatePermissionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreatePermissionProcessor::class)]
final class CreatePermissionProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private CreatePermissionProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new CreatePermissionProcessor($this->commandBus);
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessCreatesPermission(): void
  {
    $permissionId = '550e8400-e29b-41d4-a716-446655440030';

    $result = new CreatePermissionResult(
      id: $permissionId,
      name: 'users.create',
      description: 'Create users',
      createdAt: '2025-01-03 12:00:00',
    );

    $input = new PermissionInput();
    $input->name = 'users.create';
    $input->description = 'Create users';

    $this->commandBus->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (CreatePermissionCommand $command) => 'users.create' === $command->name
          && 'Create users' === $command->description,
      ))
      ->willReturn($result);

    $output = $this->processor->process($input, new Post());

    $this->assertInstanceOf(PermissionOutput::class, $output);
    $this->assertSame($permissionId, $output->id);
    $this->assertSame('users.create', $output->name);
  }
  // #endregion
}
