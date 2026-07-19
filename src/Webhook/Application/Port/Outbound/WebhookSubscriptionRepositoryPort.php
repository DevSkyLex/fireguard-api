<?php

declare(strict_types=1);

namespace Webhook\Application\Port\Outbound;

use Webhook\Domain\Model\Subscription\WebhookSubscription;
use Webhook\Domain\ValueObject\WebhookSubscriptionId;

/**
 * Port WebhookSubscriptionRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WebhookSubscriptionRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Creates or updates a webhook subscription from its current aggregate state.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscription $subscription the webhook subscription aggregate
   */
  public function save(WebhookSubscription $subscription): void;

  /**
   * Method remove.
   *
   * Deletes a webhook subscription.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscription $subscription the webhook subscription aggregate to delete
   */
  public function remove(WebhookSubscription $subscription): void;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param WebhookSubscriptionId $id the webhook subscription identifier
   *
   * @return ?WebhookSubscription the subscription, or null when not found
   */
  public function findById(WebhookSubscriptionId $id): ?WebhookSubscription;

  /**
   * Method listByOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<WebhookSubscription> the matching subscriptions, most recent first
   */
  public function listByOrganization(string $organizationId, int $limit, int $offset): array;

  /**
   * Method countByOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return int the matching subscription count
   */
  public function countByOrganization(string $organizationId): int;

  /**
   * Method countActiveByOrganization.
   *
   * Used by `CreateWebhookSubscriptionHandler` to enforce the
   * per-organization active subscription cap.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return int the active subscription count
   */
  public function countActiveByOrganization(string $organizationId): int;

  /**
   * Method findActiveByOrganizationAndEventType.
   *
   * Resolves every active subscription for an organization that has
   * subscribed to the given public event type. Used by
   * `DispatchWebhookEventHandler` to fan out one delivery per match.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $eventType the public event type
   *
   * @return list<WebhookSubscription> the matching active subscriptions
   */
  public function findActiveByOrganizationAndEventType(string $organizationId, string $eventType): array;
  // #endregion
}
