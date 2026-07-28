<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\Adapter\Messenger;

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
        $captured = $message;

        return new Envelope($message);
      });

    $adapter = new MessengerWebhookDeliveryQueueAdapter($messageBus);

    $adapter->dispatch('018f0b68-6758-7a12-8a1d-3f0d97f64a10');

    self::assertInstanceOf(DeliverWebhookCommand::class, $captured);
    self::assertSame('018f0b68-6758-7a12-8a1d-3f0d97f64a10', $captured->deliveryId);
  }
}
