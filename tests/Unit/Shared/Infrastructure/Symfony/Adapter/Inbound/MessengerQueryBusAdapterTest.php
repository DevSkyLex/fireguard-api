<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Inbound;

use Exception;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Shared\Infrastructure\Exception\{MessengerRuntimeException, NoHandlerResultException};
use Shared\Infrastructure\Symfony\Adapter\Inbound\MessengerQueryBusAdapter;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(className: MessengerQueryBusAdapter::class)]
final class MessengerQueryBusAdapterTest extends TestCase
{
  private MessageBusInterface&MockObject $messageBus;

  private MessengerQueryBusAdapter $adapter;

  protected function setUp(): void
  {
    $this->messageBus = $this->createMock(MessageBusInterface::class);
    $this->adapter = new MessengerQueryBusAdapter($this->messageBus);
  }

  #[Test]
  public function testAskSuccess(): void
  {
    $query = $this->createMock(QueryMessage::class);
    $result = $this->createMock(ResultMessage::class);
    $handledStamp = new HandledStamp($result, 'handler');
    $envelope = new Envelope($query, [$handledStamp]);

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($query)
      ->willReturn($envelope);

    $actualResult = $this->adapter->ask($query);

    $this->assertSame($result, $actualResult);
  }

  #[Test]
  public function testAskThrowsMessengerRuntimeException(): void
  {
    $query = $this->createMock(QueryMessage::class);
    $exception = new Exception('Dispatch error');

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($query)
      ->willThrowException($exception);

    $this->expectException(MessengerRuntimeException::class);
    $this->adapter->ask($query);
  }

  #[Test]
  public function testAskThrowsNoHandlerResultExceptionWhenNoStamp(): void
  {
    $query = $this->createMock(QueryMessage::class);
    $envelope = new Envelope($query); // No HandledStamp

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($query)
      ->willReturn($envelope);

    $this->expectException(NoHandlerResultException::class);
    $this->adapter->ask($query);
  }

  #[Test]
  public function testAskThrowsNoHandlerResultExceptionWhenResultNotResultMessage(): void
  {
    $query = $this->createMock(QueryMessage::class);
    $handledStamp = new HandledStamp('not-a-result-message', 'handler');
    $envelope = new Envelope($query, [$handledStamp]);

    $this->messageBus->expects($this->once())
      ->method('dispatch')
      ->with($query)
      ->willReturn($envelope);

    $this->expectException(NoHandlerResultException::class);
    $this->adapter->ask($query);
  }
}
