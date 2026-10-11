<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Delivery\RedeliverWebhook;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Webhook\Application\Port\Outbound\Internal\{WebhookDeliveryRepositoryPort, WebhookDeliveryWorkPort, WebhookSubscriptionRepositoryPort};
use Webhook\Application\Port\Outbound\WebhookDeliverySchedulePort;
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
   * @param WebhookDeliverySchedulePort $queue the delivery schedule port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param WebhookDeliveryWorkPort $work the fenced delivery state
   * @param TransactionManagerPort $transactionManager the main transaction boundary
   */
  public function __construct(
    private WebhookSubscriptionRepositoryPort $subscriptionRepository,
    private WebhookDeliveryRepositoryPort $deliveryRepository,
    private WebhookDeliverySchedulePort $queue,
    private OrganizationAuthorizationPort $authorization,
    private WebhookDeliveryWorkPort $work,
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

    $this->transactionManager->transactional(function () use ($command, $deliveryId): void {
      $now = new DateTimeImmutable();
      $generation = $this->work->reopen((string) $deliveryId, $command->subscriptionId, $now);

      if (null === $generation) {
        throw WebhookDeliveryNotFoundException::withId($command->deliveryId);
      }
      $this->queue->enqueue((string) $deliveryId, $generation, $now);
    });

    return new RedeliverWebhookResult(
      deliveryId: (string) $deliveryId,
      subscriptionId: $command->subscriptionId,
    );
  }
  // #endregion
}
