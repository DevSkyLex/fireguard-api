<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Inbound;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Message\QueryMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerQueryBusAdapter;
use Shared\Infrastructure\Symfony\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Symfony\Exception\NoHandlerResultException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Test MessengerQueryBusAdapterTest
 * @final
 *
 * Test the MessengerQueryBusAdapter class
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessengerQueryBusAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testAskReturnsResultMessage
   *
   * Test that the ask method returns a result message
   *
   * @access public
   *
   * @return void No return value
   *
   * @throws MessengerRuntimeException When the query bus throws an exception
   */
  public function testAskReturnsResultMessage(): void
  {
    $query = new DummyQuery();
    $result = new QueryResultStub('ok');
    $envelope = new Envelope(
      message: $query,
      stamps: [new HandledStamp(
        result: $result,
        handlerName: 'handler'
      )]
    );

    $queryBus = $this->createMock(type: MessageBusInterface::class);
    $queryBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(constraint: $query)
      ->willReturn(value: $envelope);

    $adapter = new MessengerQueryBusAdapter(queryBus: $queryBus);

    self::assertSame(
      expected: $result,
      actual: $adapter->ask(query: $query)
    );
  }

  /**
   * Method testAskWrapsMessengerExceptions
   *
   * Test that the ask method wraps
   * messenger exceptions
   *
   * @access public
   *
   * @return void No return value
   *
   * @throws MessengerRuntimeException When the query bus throws an exception
   */
  public function testAskWrapsMessengerExceptions(): void
  {
    $query = new DummyQuery();

    $queryBus = $this->createMock(type: MessageBusInterface::class);
    $queryBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(constraint: $query)
      ->willThrowException(exception: new RuntimeException(message: 'failure'));

    $adapter = new MessengerQueryBusAdapter(queryBus: $queryBus);

    $this->expectException(exception: MessengerRuntimeException::class);

    $adapter->ask(query: $query);
  }

  /**
   * Method testAskThrowsWhenHandledStampMissing
   *
   * Test that the ask method throws when the
   * handled stamp is missing
   *
   * @access public
   *
   * @return void No return value
   *
   * @throws NoHandlerResultException When the handled stamp is missing
   */
  public function testAskThrowsWhenHandledStampMissing(): void
  {
    $query = new DummyQuery();
    $envelope = new Envelope(message: $query);

    $queryBus = $this->createMock(type: MessageBusInterface::class);
    $queryBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(constraint: $query)
      ->willReturn(value: $envelope);

    $adapter = new MessengerQueryBusAdapter(queryBus: $queryBus);

    $this->expectException(exception: NoHandlerResultException::class);

    $adapter->ask(query: $query);
  }

  /**
   * Method testAskThrowsWhenResultIsNotResultMessage
   *
   * Test that the ask method throws when
   * the result is not a result message
   *
   * @access public
   *
   * @return void No return value
   *
   * @throws NoHandlerResultException When the result is not a result message
   */
  public function testAskThrowsWhenResultIsNotResultMessage(): void
  {
    $query = new DummyQuery();
    $envelope = new Envelope(
      message: $query,
      stamps: [new HandledStamp(
        result: new stdClass(),
        handlerName: 'handler'
      )]
    );

    $queryBus = $this->createMock(type: MessageBusInterface::class);
    $queryBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->with(constraint: $query)
      ->willReturn(value: $envelope);

    $adapter = new MessengerQueryBusAdapter(queryBus: $queryBus);

    $this->expectException(NoHandlerResultException::class);

    $adapter->ask(query: $query);
  }
  //#endregion
}

/**
 * Class DummyQuery
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DummyQuery implements QueryMessage {}

/**
 * Class QueryResultStub
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class QueryResultStub implements ResultMessage
{
  //#region Constructors
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * QueryResultStub class
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The value
   */
  public function __construct(
    public readonly string $value
  ) {}
  //#endregion
}
