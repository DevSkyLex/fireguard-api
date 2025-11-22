<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Shared\Infrastructure\Exception\NoHandlerResultException;
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerCommandBusAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Exception;

#[CoversClass(className: MessengerCommandBusAdapter::class)]
final class MessengerCommandBusAdapterTest extends TestCase
{
  private MessageBusInterface&MockObject $messageBus;
  private MessengerCommandBusAdapter $adapter;

  protected function setUp(): void
  {
    $this->messageBus = $this->createMock(MessageBusInterface::class);
    $this->adapter = new MessengerCommandBusAdapter($this->messageBus);
  }

  #[Test]
  public function testDispatchSuccess(): void
  {
    $command = $this->createMock(CommandMessage::class);
    $result = $this->createMock(ResultMessage::class);
    $handledStamp = new HandledStamp($result, 'handler');
    $envelope = new Envelope($command, [$handledStamp]);

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($command)
      ->willReturn($envelope);

    $actualResult = $this->adapter->dispatch($command);

    $this->assertSame($result, $actualResult);
  }

  #[Test]
  public function testDispatchThrowsMessengerRuntimeException(): void
  {
    $command = $this->createMock(CommandMessage::class);
    $exception = new Exception('Dispatch error');

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($command)
      ->willThrowException($exception);

    $this->expectException(MessengerRuntimeException::class);
    $this->adapter->dispatch($command);
  }

  #[Test]
  public function testDispatchThrowsNoHandlerResultExceptionWhenNoStamp(): void
  {
    $command = $this->createMock(CommandMessage::class);
    $envelope = new Envelope($command); // No HandledStamp

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($command)
      ->willReturn($envelope);

    $this->expectException(NoHandlerResultException::class);
    $this->adapter->dispatch($command);
  }

  #[Test]
  public function testDispatchThrowsNoHandlerResultExceptionWhenResultNotResultMessage(): void
  {
    $command = $this->createMock(CommandMessage::class);
    $handledStamp = new HandledStamp('not-a-result-message', 'handler');
    $envelope = new Envelope($command, [$handledStamp]);

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($command)
      ->willReturn($envelope);

    $this->expectException(NoHandlerResultException::class);
    $this->adapter->dispatch($command);
  }
}

