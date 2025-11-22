<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Message\ResultMessage;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Exception\NoHandlerResultException;
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerEventListenerAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Exception;
use stdClass;

#[CoversClass(className: MessengerEventListenerAdapter::class)]
final class MessengerEventListenerAdapterTest extends TestCase
{
  private MessageBusInterface&MockObject $messageBus;
  private MessengerEventListenerAdapter $adapter;

  protected function setUp(): void
  {
    $this->messageBus = $this->createMock(MessageBusInterface::class);
    $this->adapter = new MessengerEventListenerAdapter($this->messageBus);
  }

  #[Test]
  public function testHandleSuccess(): void
  {
    $event = new stdClass();
    $result = $this->createMock(ResultMessage::class);
    $handledStamp = new HandledStamp($result, 'handler');
    $envelope = new Envelope($event, [$handledStamp]);

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($event)
      ->willReturn($envelope);

    $actualResult = $this->adapter->handle($event);

    $this->assertSame($result, $actualResult);
  }

  #[Test]
  public function testHandleReturnsNullWhenNoHandledStamp(): void
  {
    $event = new stdClass();
    $envelope = new Envelope($event); // No HandledStamp

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($event)
      ->willReturn($envelope);

    $this->assertNull($this->adapter->handle($event));
  }

  #[Test]
  public function testHandleReturnsNullWhenResultIsNull(): void
  {
    $event = new stdClass();
    $handledStamp = new HandledStamp(null, 'handler');
    $envelope = new Envelope($event, [$handledStamp]);

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($event)
      ->willReturn($envelope);

    $this->assertNull($this->adapter->handle($event));
  }

  #[Test]
  public function testHandleThrowsMessengerRuntimeException(): void
  {
    $event = new stdClass();
    $exception = new Exception('Dispatch error');

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($event)
      ->willThrowException($exception);

    $this->expectException(MessengerRuntimeException::class);
    $this->adapter->handle($event);
  }

  #[Test]
  public function testHandleThrowsNoHandlerResultExceptionWhenResultInvalid(): void
  {
    $event = new stdClass();
    $handledStamp = new HandledStamp('invalid-result', 'handler');
    $envelope = new Envelope($event, [$handledStamp]);

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($event)
      ->willReturn($envelope);

    $this->expectException(NoHandlerResultException::class);
    $this->adapter->handle($event);
  }
}

