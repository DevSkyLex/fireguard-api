<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\PingWebhookSubscription;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Webhook\Application\Contract\Event\{WebhookEventType, WebhookPayloadEnvelope};
use Webhook\Application\Port\Outbound\{WebhookDeliveryQueuePort, WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Domain\Exception\{WebhookSubscriptionNotFoundException, WebhookValidationException};
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookSubscriptionId};

/**
 * UseCase PingWebhookSubscriptionHandler.
 *
 * `POST /webhooks/subscriptions/{id}/ping`: enqueues a single synthetic
 * test delivery through the exact same pipeline a real curated event
 * would use (reserve a delivery row, enqueue `DeliverWebhookCommand`),
 * bypassing the fan-out (`DispatchWebhookEventHandler`) since it targets
 * exactly one subscription. Uses the reserved `WebhookEventType::PING`
 * public type, which is excluded from the real allowlist.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PingWebhookSubscriptionHandler implements CommandHandler
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
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param UuidFactory $uuidFactory the uuid factory
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookDeliveryRepositoryPort $deliveryRepository,
    private WebhookDeliveryQueuePort $queue,
    private OrganizationAuthorizationPort $authorization,
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
   * @param PingWebhookSubscriptionCommand $command the command payload
   *
   * @return PingWebhookSubscriptionResult the use case result
   */
  public function __invoke(PingWebhookSubscriptionCommand $command): PingWebhookSubscriptionResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.webhooks.manage',
    ]);

    $subscriptionId = WebhookSubscriptionId::fromString($command->subscriptionId);
    $subscription = $this->subscriptionRepository->findById($subscriptionId);

    if (null === $subscription || $subscription->organizationId() !== $command->organizationId) {
      throw WebhookSubscriptionNotFoundException::withId($command->subscriptionId);
    }

    $eventId = $this->uuidFactory->generateRaw();
    $now = new DateTimeImmutable();

    /** @var WebhookDeliveryId $deliveryId */
    $deliveryId = $this->uuidFactory->create(WebhookDeliveryId::class);

    $envelope = new WebhookPayloadEnvelope(
      id: (string) $deliveryId,
      type: WebhookEventType::PING->value,
      created: $now->format('c'),
      organizationId: $command->organizationId,
      data: [
        'message' => 'This is a test delivery from FireGuard.',
        'subscriptionId' => $command->subscriptionId,
      ],
    );

    $reserved = $this->deliveryRepository->reserve(
      id: $deliveryId,
      subscriptionId: $subscriptionId,
      organizationId: $command->organizationId,
      eventType: WebhookEventType::PING->value,
      eventId: $eventId,
      payload: $envelope->toArray(),
    );

    if (!$reserved) {
      throw new WebhookValidationException('Could not enqueue the test delivery — please retry.');
    }

    $this->queue->dispatch((string) $deliveryId);

    return new PingWebhookSubscriptionResult(
      deliveryId: (string) $deliveryId,
      subscriptionId: $command->subscriptionId,
    );
  }
  // #endregion
}
