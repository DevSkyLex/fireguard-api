<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Inbound;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Message\ResultMessage;
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerEventListenerAdapter;
use Shared\Infrastructure\Symfony\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Symfony\Exception\NoHandlerResultException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Test MessengerEventListenerAdapter
 *
 * Test the MessengerEventListenerAdapter class
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessengerEventListenerAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testHandleReturnsResultMessageWhenPresent
   *
   * Test the handle method when the event
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testHandleReturnsResultMessageWhenPresent(): void
  {
    $event = new stdClass();
    $result = new EventResultStub('ok');
    $envelope = new Envelope(
      message: $event,
      stamps: [new HandledStamp(
        result: $result,
        handlerName: 'handler'
      )]
    );

    $eventBus = $this->createMock(type: MessageBusInterface::class);
    $eventBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $event)
      ->willReturn(value: $envelope);

    $adapter = new MessengerEventListenerAdapter(eventBus: $eventBus);

    self::assertSame(
      expected: $result,
      actual: $adapter->handle(event: $event)
    );
  }

  /**
   * Method testHandleReturnsNullWhenNoHandledStamp
   *
   * Test the handle method when the event
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testHandleReturnsNullWhenNoHandledStamp(): void
  {
    $event = new stdClass();
    $envelope = new Envelope(message: $event);

    $eventBus = $this->createMock(type: MessageBusInterface::class);
    $eventBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $event)
      ->willReturn(value: $envelope);

    $adapter = new MessengerEventListenerAdapter(eventBus: $eventBus);

    self::assertNull(actual: $adapter->handle(event: $event));
  }

  /**
   * Method testHandleReturnsNullWhenHandledResultIsNull
   *
   * Test the handle method when the event
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testHandleReturnsNullWhenHandledResultIsNull(): void
  {
    $event = new stdClass();
    $envelope = new Envelope(
      message: $event,
      stamps: [new HandledStamp(
        result: null,
        handlerName: 'handler'
      )]
    );

    $eventBus = $this->createMock(type: MessageBusInterface::class);
    $eventBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $event)
      ->willReturn(value: $envelope);

    $adapter = new MessengerEventListenerAdapter(eventBus: $eventBus);

    self::assertNull(actual: $adapter->handle(event: $event));
  }

  /**
   * Method testHandleThrowsWhenResultIsNotResultMessage
   *
   * Test the handle method when the event
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testHandleThrowsWhenResultIsNotResultMessage(): void
  {
    $event = new stdClass();
    $envelope = new Envelope(
      message: $event,
      stamps: [new HandledStamp(
        result: new stdClass(),
        handlerName: 'handler'
      )]
    );

    $eventBus = $this->createMock(type: MessageBusInterface::class);
    $eventBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $event)
      ->willReturn(value: $envelope);

    $adapter = new MessengerEventListenerAdapter(eventBus: $eventBus);

    $this->expectException(exception: NoHandlerResultException::class);

    $adapter->handle(event: $event);
  }

  /**
   * Method testHandleWrapsMessengerExceptions
   *
   * Test the handle method when the event
   * is handled and returns a result
   *
   * @access public
   *
   * @return void No return value
   */
  public function testHandleWrapsMessengerExceptions(): void
  {
    $event = new stdClass();

    $eventBus = $this->createMock(type: MessageBusInterface::class);
    $eventBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(arguments: $event)
      ->willThrowException(exception: new RuntimeException(message: 'failure'));

    $adapter = new MessengerEventListenerAdapter(eventBus: $eventBus);

    $this->expectException(exception: MessengerRuntimeException::class);

    $adapter->handle(event: $event);
  }
  //#endregion
}

/**
 * Class EventResultStub
 * @final
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EventResultStub implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the event result stub
   *
   * @access public
   *
   * @param string $value The value of the event result
   */
  public function __construct(public readonly string $value) {}
  //#endregion
}
