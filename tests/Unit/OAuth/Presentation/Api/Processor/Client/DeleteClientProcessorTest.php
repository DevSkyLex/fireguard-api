<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\Client\DeleteClient\DeleteClientCommand;
use OAuth\Presentation\Api\Processor\Client\DeleteClientProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test DeleteClientProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeleteClientProcessor::class)]
final class DeleteClientProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdIsNotString(): void
  {
    $processor = new DeleteClientProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => []],
    );
  }

  #[Test]
  public function testProcessDispatchesCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (DeleteClientCommand $command): bool => 'client-123' === $command->clientId,
      ));

    $processor = new DeleteClientProcessor(commandBus: $commandBus);

    $processor->process(
      data: null,
      operation: $this->createStub(Operation::class),
      uriVariables: ['id' => 'client-123'],
    );
  }
  // #endregion
}
