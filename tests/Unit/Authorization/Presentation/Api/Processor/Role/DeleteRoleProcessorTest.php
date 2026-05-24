<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor\Role;

use ApiPlatform\Metadata\Delete;
use Authorization\Application\UseCase\Command\Role\DeleteRole\DeleteRoleCommand;
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Presentation\Api\Processor\Role\DeleteRoleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test DeleteRoleProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteRoleProcessor::class)]
final class DeleteRoleProcessorTest extends TestCase
{
  // #region Properties
  private CommandBusPort&MockObject $commandBus;

  private DeleteRoleProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->commandBus = $this->createMock(CommandBusPort::class);
    $this->processor = new DeleteRoleProcessor($this->commandBus);
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdMissing(): void
  {
    $this->commandBus->expects($this->never())->method(self::anything());

    $this->expectException(RoleNotFoundException::class);

    $this->processor->process(null, new Delete(), ['id' => null]);
  }

  #[Test]
  public function testProcessDispatchesCommand(): void
  {
    $roleId = '550e8400-e29b-41d4-a716-446655440020';

    $this->commandBus->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(
        fn (DeleteRoleCommand $command) => $command->roleId === $roleId,
      ));

    $this->processor->process(null, new Delete(), ['id' => $roleId]);
  }
  // #endregion
}
