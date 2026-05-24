<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use Exception;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Domain\Event\DomainEvent;
use Shared\Infrastructure\Symfony\Adapter\Outbound\MessengerEventBusAdapter;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};

/**
 * Class MessengerEventBusAdapterTest.
 *
 * Unit tests for the MessengerEventBusAdapter.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
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
    $event1 = $this->createStub(DomainEvent::class);
    $event2 = $this->createStub(DomainEvent::class);

    $this->messageBus->expects($this->exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function (object $message) use ($event1, $event2) {
        /** @var int $callCount */
        static $callCount = 0;
        ++$callCount;
        if (1 === $callCount) {
          $this->assertSame($event1, $message);

          return new Envelope($event1);
        }
        if (2 === $callCount) {
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
    $event = $this->createStub(DomainEvent::class);
    $exception = new Exception('Dispatch error');

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($event)
      ->willThrowException($exception);

    $this->expectException(MessengerRuntimeException::class);
    $this->adapter->publish($event);
  }
}
