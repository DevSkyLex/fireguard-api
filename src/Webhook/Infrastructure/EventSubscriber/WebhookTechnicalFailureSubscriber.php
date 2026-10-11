<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Webhook\Application\Port\Outbound\Internal\WebhookDeliveryWorkPort;
use Webhook\Application\UseCase\Command\Delivery\DeliverWebhook\DeliverWebhookCommand;

/**
 * Class WebhookTechnicalFailureSubscriber
 *
 * Quarantines exhausted retry cycles before periodic recovery can retry them.
 * A stale envelope cannot release or quarantine a newer worker's lease.
 *
 * @category EventSubscriber
 */
final readonly class WebhookTechnicalFailureSubscriber implements EventSubscriberInterface
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param WebhookDeliveryWorkPort $work
   *
   * @return void
   */
  public function __construct(private WebhookDeliveryWorkPort $work)
  {
  }

  /**
   * Method getSubscribedEvents
   *
   * @access public
   *
   * @return array<string, array{string, int}>
   */
  public static function getSubscribedEvents(): array
  {
    // Retry scheduling runs first; willRetry() is authoritative afterwards.
    return [WorkerMessageFailedEvent::class => ['onFailure', -50]];
  }

  /**
   * Method onFailure
   *
   * @access public
   *
   * @param WorkerMessageFailedEvent $event
   *
   * @return void
   */
  public function onFailure(WorkerMessageFailedEvent $event): void
  {
    $command = $event->getEnvelope()->getMessage();

    if ($command instanceof DeliverWebhookCommand && !$event->willRetry()) {
      $this->work->quarantine($command->deliveryId, $command->generation(), $command->wakeToken());
    }
  }
}
