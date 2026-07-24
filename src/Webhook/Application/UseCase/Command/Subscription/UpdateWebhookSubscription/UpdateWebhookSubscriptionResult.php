<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateWebhookSubscriptionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateWebhookSubscriptionResult implements ResultMessage
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
}
