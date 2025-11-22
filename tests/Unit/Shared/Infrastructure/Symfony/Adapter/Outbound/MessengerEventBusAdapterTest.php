<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\MessengerEventBusAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Exception;

/**
 * Class MessengerEventBusAdapterTest
 *
 * Unit tests for the MessengerEventBusAdapter.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Symfony\Adapter\Outbound\MessengerEventBusAdapter
 */
#[CoversClass(className: MessengerEventBusAdapter::class)]
final class MessengerEventBusAdapterTest extends TestCase
{
  private MessageBusInterface&MockObject $messageBus;
  private MessengerEventBusAdapter $adapter;

  /**
   * Set up the test environment.
   */
  protected function setUp(): void
  {
    $this->messageBus = $this->createMock(MessageBusInterface::class);
    $this->adapter = new MessengerEventBusAdapter($this->messageBus);
  }

  /**
   * Test that events are published successfully.
   */
  #[Test]
  public function testPublishSuccess(): void
  {
    $event1 = $this->createMock(DomainEvent::class);
    $event2 = $this->createMock(DomainEvent::class);

    $this->messageBus->expects($this->exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function ($message) use ($event1, $event2) {
        static $callCount = 0;
        $callCount++;
        if ($callCount === 1) {
          $this->assertSame($event1, $message);
          return new Envelope($event1);
        }
        if ($callCount === 2) {
          $this->assertSame($event2, $message);
          return new Envelope($event2);
        }
        return new Envelope($message);
      });

    $this->adapter->publish($event1, $event2);
  }

  /**
   * Test that publishing fails and throws a MessengerRuntimeException.
   */
  #[Test]
  public function testPublishThrowsException(): void
  {
    $event = $this->createMock(DomainEvent::class);
    $exception = new Exception('Dispatch error');

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($event)
      ->willThrowException($exception);

    $this->expectException(MessengerRuntimeException::class);
    $this->adapter->publish($event);
  }
}

