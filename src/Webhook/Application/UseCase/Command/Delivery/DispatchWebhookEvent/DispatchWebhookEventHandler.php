<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\DispatchWebhookEvent;

use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Webhook\Application\Contract\Event\WebhookPayloadEnvelope;
use Webhook\Application\Port\Outbound\{WebhookDeliveryQueuePort, WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Domain\ValueObject\WebhookDeliveryId;

/**
 * UseCase DispatchWebhookEventHandler.
 *
 * The async fan-out stage: resolves every active subscription for the
 * organization matching the given public event type, idempotently reserves
 * one `webhook_deliveries` row per match (the reservation's
 * `(subscriptionId, eventId)` uniqueness makes re-running this handler for
 * the same source event safe), and enqueues one `DeliverWebhookCommand`
 * per newly reserved delivery. Never performs the outbound POST itself —
 * that is `DeliverWebhookHandler`'s job, keeping this stage's own retry
 * cheap (list + reserve only).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DispatchWebhookEventHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionRepositoryPort $subscriptionRepository the webhook subscription repository port
   * @param WebhookDeliveryRepositoryPort $deliveryRepository the webhook delivery repository port
   * @param WebhookDeliveryQueuePort $queue the delivery queue port
   * @param UuidFactory $uuidFactory the uuid factory
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookDeliveryRepositoryPort $deliveryRepository,
    private WebhookDeliveryQueuePort $queue,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DispatchWebhookEventCommand $command the command payload
   *
   * @return VoidResult the command result
   */
  public function __invoke(DispatchWebhookEventCommand $command): VoidResult
  {
    $subscriptions = $this->subscriptionRepository->findActiveByOrganizationAndEventType(
      $command->organizationId,
      $command->eventType,
    );

    foreach ($subscriptions as $subscription) {
      /** @var WebhookDeliveryId $deliveryId */
      $deliveryId = $this->uuidFactory->create(WebhookDeliveryId::class);

      $envelope = new WebhookPayloadEnvelope(
        id: (string) $deliveryId,
        type: $command->eventType,
        created: $command->occurredAt->format('c'),
        organizationId: $command->organizationId,
        data: $command->data,
      );

      $reserved = $this->deliveryRepository->reserve(
        id: $deliveryId,
        subscriptionId: $subscription->id(),
        organizationId: $command->organizationId,
        eventType: $command->eventType,
        eventId: $command->eventId,
        payload: $envelope->toArray(),
      );

      if ($reserved) {
        $this->queue->dispatch((string) $deliveryId);
      }
    }

    return new VoidResult();
  }
  // #endregion
}
