<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Symfony\Adapter\Outbound\MessengerEventBusAdapter;
use Shared\Infrastructure\Symfony\Exception\MessengerRuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Test MessengerEventBusAdapter
 * @final
 *
 * Test the MessengerEventBusAdapter class.
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessengerEventBusAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testPublishDispatchesEachEvent
   * @method testPublishDispatchesEachEvent(): void
   *
   * Test the publish method when the event
   * is dispatched
   *
   * @access public
   *
   * @return void No return value
   */
  public function testPublishDispatchesEachEvent(): void
  {
    $eventBus = $this->createMock(type: MessageBusInterface::class);
    $eventBus->expects(self::exactly(2))
      ->method(constraint: 'dispatch')
      ->with(self::isInstanceOf(DomainEvent::class))
      ->willReturnCallback(static fn(object $message) => new \Symfony\Component\Messenger\Envelope($message));

    $adapter = new MessengerEventBusAdapter(eventBus: $eventBus);

    $adapter->publish(
      new DummyDomainEvent('1'),
      new DummyDomainEvent('2')
    );
  }

  /**
   * Method testPublishWrapsMessengerExceptions
   * @method testPublishWrapsMessengerExceptions(): void
   *
   * Test the publish method when the event
   * is dispatched
   *
   * @access public
   *
   * @return void No return value
   */
  public function testPublishWrapsMessengerExceptions(): void
  {
    $eventBus = $this->createMock(type: MessageBusInterface::class);
    $eventBus->expects(self::once())
      ->method(constraint: 'dispatch')
      ->willThrowException(new class extends \RuntimeException implements Throwable {});

    $adapter = new MessengerEventBusAdapter(eventBus: $eventBus);

    $this->expectException(MessengerRuntimeException::class);

    $adapter->publish(new DummyDomainEvent('1'));
  }
  //#endregion
}

/**
 * Class DummyDomainEvent
 * @final
 *
 * Dummy domain event used for testing dispatching.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DummyDomainEvent implements DomainEvent
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the dummy domain event
   *
   * @access public
   *
   * @param string $id The id of the dummy domain event
   */
  public function __construct(private readonly string $id) {}
  //#endregion

  //#region Methods
  /**
   * Method eventId
   * @method eventId(): string
   *
   * Get the id of the dummy
   * domain event
   *
   * @access public
   *
   * @return string The id of the dummy domain event
   */
  public function eventId(): string
  {
    return $this->id;
  }

  /**
   * Method occurredAt
   * @method occurredAt(): DateTimeImmutable
   *
   * Get the occurred at of the
   * dummy domain event
   *
   * @access public
   *
   * @return DateTimeImmutable The occurred at of the dummy domain event
   */
  public function occurredAt(): DateTimeImmutable
  {
    return new DateTimeImmutable();
  }
  //#endregion
}
