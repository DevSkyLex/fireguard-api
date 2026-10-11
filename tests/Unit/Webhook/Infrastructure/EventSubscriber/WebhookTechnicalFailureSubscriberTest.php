<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Infrastructure\EventSubscriber;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Webhook\Application\Port\Outbound\Internal\WebhookDeliveryWorkPort;
use Webhook\Application\UseCase\Command\Delivery\DeliverWebhook\DeliverWebhookCommand;
use Webhook\Infrastructure\EventSubscriber\WebhookTechnicalFailureSubscriber;

/** Quarantine belongs to a generation and follows Messenger's bounded retry decision. */
final class WebhookTechnicalFailureSubscriberTest extends TestCase
{
  #[Test]
  public function quarantinesOnlyWhenMessengerWillNotRetry(): void
  {
    $work = $this->createMock(WebhookDeliveryWorkPort::class);
    $work->expects(self::once())->method('quarantine')->with('delivery', 7);
    $subscriber = new WebhookTechnicalFailureSubscriber($work);
    $event = new WorkerMessageFailedEvent(new Envelope(new DeliverWebhookCommand('delivery', 7)), 'webhook', new RuntimeException('Unexpected failure'));
    $subscriber->onFailure($event);
  }

  #[Test]
  public function leavesRetryableTechnicalFailureToMessenger(): void
  {
    $work = $this->createMock(WebhookDeliveryWorkPort::class);
    $work->expects(self::never())->method('quarantine');
    $event = new WorkerMessageFailedEvent(new Envelope(new DeliverWebhookCommand('delivery', 7)), 'webhook', new RuntimeException('Unexpected failure'));
    $event->setForRetry();
    new WebhookTechnicalFailureSubscriber($work)->onFailure($event);
  }
}
