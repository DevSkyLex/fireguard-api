<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor\Permission;

use ApiPlatform\Metadata\Put;
use Authorization\Application\UseCase\Command\Permission\UpdatePermission\{UpdatePermissionCommand, UpdatePermissionResult};
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Presentation\Api\Dto\Input\Permission\PermissionInput;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Authorization\Presentation\Api\Processor\Permission\UpdatePermissionProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test UpdatePermissionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdatePermissionProcessor::class)]
final class UpdatePermissionProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private UpdatePermissionProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new UpdatePermissionProcessor($this->commandBus);
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdMissing(): void
  {
    $this->commandBus->expects($this->never())->method(self::anything());

    $this->expectException(PermissionNotFoundException::class);

    $this->processor->process(new PermissionInput(), new Put(), ['id' => null]);
  }

  #[Test]
  public function testProcessUpdatesPermission(): void
  {
    $permissionId = '550e8400-e29b-41d4-a716-446655440040';

    $result = new UpdatePermissionResult(
      id: $permissionId,
      name: 'users.read',
      description: 'Read users',
      createdAt: '2025-01-04 12:00:00',
    );

    $this->commandBus->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (UpdatePermissionCommand $command) => $command->permissionId === $permissionId,
      ))
      ->willReturn($result);

    $output = $this->processor->process(new PermissionInput(), new Put(), ['id' => $permissionId]);

    $this->assertInstanceOf(PermissionOutput::class, $output);
    $this->assertSame($permissionId, $output->id);
    $this->assertSame('users.read', $output->name);
  }
  // #endregion
}
