<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Delete;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\UseCase\Command\User\DeleteUser\DeleteUserCommand;
use User\Presentation\Api\Processor\User\DeleteUserProcessor;

/**
 * Test DeleteUserProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteUserProcessor::class)]
final class DeleteUserProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessReturnsWhenIdMissing(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())
      ->method('dispatch');

    $processor = new DeleteUserProcessor($commandBus);

    $processor->process(null, new Delete(), ['id' => null]);
  }

  #[Test]
  public function testProcessDispatchesCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (DeleteUserCommand $command) => 'user-1' === $command->id,
      ));

    $processor = new DeleteUserProcessor($commandBus);

    $processor->process(null, new Delete(), ['id' => 'user-1']);
  }
  // #endregion
}
