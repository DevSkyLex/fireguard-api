<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Inbound;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerCommandBusAdapter;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Exception\NoHandlerResultException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Test MessengerCommandBusAdapter
 * @final
 *
 * Test the MessengerCommandBusAdapter class
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessengerCommandBusAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testDispatchReturnsResultMessage
   *
   * Test the dispatch method when the command
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDispatchReturnsResultMessage(): void
  {
    $command = new DummyCommand();
    $result = new CommandResultStub(value: 'ok');
    $envelope = new Envelope(
      message: $command,
      stamps: [new HandledStamp(
        result: $result,
        handlerName: 'handler'
      )]
    );

    $commandBus = $this->createMock(type: MessageBusInterface::class);
    $commandBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $command)
      ->willReturn(value: $envelope);

    $adapter = new MessengerCommandBusAdapter(commandBus: $commandBus);

    self::assertSame(
      expected: $result,
      actual: $adapter->dispatch(command: $command)
    );
  }

  /**
   * Method testDispatchWrapsMessengerExceptions
   *
   * Test the dispatch method when the command
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDispatchWrapsMessengerExceptions(): void
  {
    $command = new DummyCommand();

    $commandBus = $this->createMock(type: MessageBusInterface::class);
    $commandBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $command)
      ->willThrowException(exception: new RuntimeException(
        message: 'failure'
      ));

    $adapter = new MessengerCommandBusAdapter(commandBus: $commandBus);

    $this->expectException(exception: MessengerRuntimeException::class);

    $adapter->dispatch(command: $command);
  }

  /**
   * Method testDispatchThrowsWhenHandledStampMissing
   *
   * Test the dispatch method when the command
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDispatchThrowsWhenHandledStampMissing(): void
  {
    $command = new DummyCommand();
    $envelope = new Envelope(message: $command);

    $commandBus = $this->createMock(type: MessageBusInterface::class);
    $commandBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $command)
      ->willReturn(value: $envelope);

    $adapter = new MessengerCommandBusAdapter(commandBus: $commandBus);

    $this->expectException(exception: NoHandlerResultException::class);

    $adapter->dispatch(command: $command);
  }

  /**
   * Method testDispatchThrowsWhenResultIsNotResultMessage
   *
   * Test the dispatch method when the command
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testDispatchThrowsWhenResultIsNotResultMessage(): void
  {
    $command = new DummyCommand();
    $envelope = new Envelope(
      message: $command,
      stamps: [new HandledStamp(
        result: new stdClass(),
        handlerName: 'handler'
      )]
    );

    $commandBus = $this->createMock(type: MessageBusInterface::class);
    $commandBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $command)
      ->willReturn(value: $envelope);

    $adapter = new MessengerCommandBusAdapter(commandBus: $commandBus);

    $this->expectException(exception: NoHandlerResultException::class);

    $adapter->dispatch(command: $command);
  }
  //#endregion
}

/**
 * Class DummyCommand
 * @final
 *
 * @category Infrastructure Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DummyCommand implements CommandMessage {}

/**
 * Class CommandResultStub
 * @final
 *
 * @category Infrastructure Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CommandResultStub implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the command result stub
   *
   * @access public
   *
   * @param string $value The value of the command result
   */
  public function __construct(
    public readonly string $value
  ) {}
  //#endregion
}
