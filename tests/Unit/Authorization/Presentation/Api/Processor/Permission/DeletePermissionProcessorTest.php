<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor\Permission;

use ApiPlatform\Metadata\Delete;
use Authorization\Application\UseCase\Command\Permission\DeletePermission\DeletePermissionCommand;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Presentation\Api\Processor\Permission\DeletePermissionProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test DeletePermissionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeletePermissionProcessor::class)]
final class DeletePermissionProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private DeletePermissionProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new DeletePermissionProcessor($this->commandBus);
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdMissing(): void
  {
    $this->expectException(PermissionNotFoundException::class);

    $this->processor->process(null, new Delete(), ['id' => null]);
  }

  #[Test]
  public function testProcessDispatchesCommand(): void
  {
    $permissionId = '550e8400-e29b-41d4-a716-446655440050';

    $this->commandBus->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (DeletePermissionCommand $command) => $command->permissionId === $permissionId,
      ));

    $this->processor->process(null, new Delete(), ['id' => $permissionId]);
  }
  // #endregion
}
