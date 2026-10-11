<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Adapter\Messenger;

use Doctrine\DBAL\Connection;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Webhook\Application\UseCase\Command\Delivery\DeliverWebhook\DeliverWebhookCommand;
use Webhook\Infrastructure\Adapter\Messenger\MessengerWebhookDeliveryQueueAdapter;

/**
 * Test MessengerWebhookDeliveryQueueAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessengerWebhookDeliveryQueueAdapter::class)]
final class MessengerWebhookDeliveryQueueAdapterTest extends TestCase
{
  #[Test]
  public function testDispatchPutsADeliverWebhookCommandOnTheBus(): void
  {
    $captured = null;
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $message) use (&$captured): Envelope {
        $captured = $message instanceof Envelope ? $message->getMessage() : $message;

        return $message instanceof Envelope ? $message : new Envelope($message);
      });

    $connection = $this->createStub(Connection::class);
    $connection->method('isTransactionActive')->willReturn(true);
    $adapter = new MessengerWebhookDeliveryQueueAdapter($messageBus, $connection);

    $adapter->dispatch('018f0b68-6758-7a12-8a1d-3f0d97f64a10');

    self::assertInstanceOf(DeliverWebhookCommand::class, $captured);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a10', $captured->deliveryId);
  }

  #[Test]
  public function refusesEnqueueWithoutTheMainTransaction(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $bus = $this->createMock(MessageBusInterface::class);
    $bus->expects(self::never())->method('dispatch');
    $this->expectException(LogicException::class);
    new MessengerWebhookDeliveryQueueAdapter($bus, $connection)->dispatch('delivery');
  }
}
