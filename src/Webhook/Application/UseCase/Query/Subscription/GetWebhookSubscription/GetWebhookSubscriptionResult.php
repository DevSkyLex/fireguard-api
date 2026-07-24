<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Query\Subscription\GetWebhookSubscription;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;
use Webhook\Domain\Model\Subscription\WebhookSubscription;

/**
 * UseCase GetWebhookSubscriptionResult.
 *
 * Never carries the signing secret — reused as the per-item view by
 * `ListWebhookSubscriptionsResult`, mirroring how `Import\Application\UseCase\Query\GetImportJob\GetImportJobResult`
 * is reused inside `ListImportJobsResult`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetWebhookSubscriptionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the subscription identifier
   * @param string $organizationId the owning organization identifier
   * @param string $url the target URL
   * @param list<string> $eventTypes the subscribed public event type allowlist
   * @param bool $isActive whether deliveries are currently enqueued
   * @param string $description the free-form description
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $url,
    public array $eventTypes,
    public bool $isActive,
    public string $description,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param WebhookSubscription $subscription the webhook subscription aggregate
   *
   * @return self the view built from the aggregate
   */
  public static function fromDomain(WebhookSubscription $subscription): self
  {
    return new self(
      id: (string) $subscription->id(),
      organizationId: $subscription->organizationId(),
      url: $subscription->url(),
      eventTypes: $subscription->eventTypes(),
      isActive: $subscription->isActive(),
      description: $subscription->description(),
      createdAt: $subscription->createdAt(),
      updatedAt: $subscription->updatedAt(),
    );
  }
  // #endregion
}
