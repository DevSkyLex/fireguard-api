<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\PingWebhookSubscription;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};
use Webhook\Application\Contract\Event\{WebhookEventType, WebhookPayloadEnvelope};
use Webhook\Application\Port\Outbound\Internal\{WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\Port\Outbound\WebhookDeliverySchedulePort;
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
   * @param WebhookDeliverySchedulePort $queue the delivery schedule port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param UuidGeneratorPort $uuidFactory the UUID generator
   * @param TransactionManagerPort $transactionManager the main transaction boundary
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookDeliveryRepositoryPort $deliveryRepository,
    private WebhookDeliverySchedulePort $queue,
    private OrganizationAuthorizationPort $authorization,
    private UuidGeneratorPort $uuidFactory,
    private TransactionManagerPort $transactionManager,
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

    $eventId = $this->uuidFactory->generate();
    $now = new DateTimeImmutable();

    $deliveryId = WebhookDeliveryId::fromString($this->uuidFactory->generate());

    $envelope = new WebhookPayloadEnvelope(
      id: (string) $deliveryId,
      type: WebhookEventType::PING->value,
      created: $now->format('c'),
      organizationId: $command->organizationId,
      data: [
        'message' => 'This is a test delivery from Fireguard.',
        'subscriptionId' => $command->subscriptionId,
      ],
    );

    $this->transactionManager->transactional(function () use ($deliveryId, $subscriptionId, $command, $eventId, $envelope, $now): void {
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

      $this->queue->enqueue((string) $deliveryId, 0, $now);
    });

    return new PingWebhookSubscriptionResult(
      deliveryId: (string) $deliveryId,
      subscriptionId: $command->subscriptionId,
    );
  }
  // #endregion
}
