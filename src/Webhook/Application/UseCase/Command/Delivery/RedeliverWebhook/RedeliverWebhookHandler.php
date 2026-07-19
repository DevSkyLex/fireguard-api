<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Webhook\Application\Port\Outbound\{WebhookDeliveryQueuePort, WebhookDeliveryRepositoryPort, WebhookSubscriptionRepositoryPort};
use Webhook\Domain\Exception\{WebhookDeliveryNotFoundException, WebhookSubscriptionNotFoundException};
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookSubscriptionId};

/**
 * UseCase RedeliverWebhookHandler.
 *
 * Reopens a delivery (any status, including terminal `failed`) back to
 * `pending` and re-enqueues it, mirroring `DeliverWebhookHandler`'s
 * idempotent reservation — a manual, on-demand retry outside Messenger's
 * own backoff schedule.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RedeliverWebhookHandler implements CommandHandler
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
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookDeliveryRepositoryPort $deliveryRepository,
    private WebhookDeliveryQueuePort $queue,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param RedeliverWebhookCommand $command the command payload
   *
   * @return RedeliverWebhookResult the use case result
   */
  public function __invoke(RedeliverWebhookCommand $command): RedeliverWebhookResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.webhooks.manage',
    ]);

    $subscriptionId = WebhookSubscriptionId::fromString($command->subscriptionId);
    $subscription = $this->subscriptionRepository->findById($subscriptionId);

    if (null === $subscription || $subscription->organizationId() !== $command->organizationId) {
      throw WebhookSubscriptionNotFoundException::withId($command->subscriptionId);
    }

    $deliveryId = WebhookDeliveryId::fromString($command->deliveryId);
    $delivery = $this->deliveryRepository->findById($deliveryId);

    if (null === $delivery || (string) $delivery->subscriptionId() !== $command->subscriptionId) {
      throw WebhookDeliveryNotFoundException::withId($command->deliveryId);
    }

    $delivery->reopen(new DateTimeImmutable());
    $this->deliveryRepository->save($delivery);

    $this->queue->dispatch((string) $deliveryId);

    return new RedeliverWebhookResult(
      deliveryId: (string) $deliveryId,
      subscriptionId: $command->subscriptionId,
    );
  }
  // #endregion
}
