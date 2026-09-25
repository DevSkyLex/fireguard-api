<?php

declare(strict_types=1);

namespace Webhook\Domain\Model\Subscription;

use DateTimeImmutable;

/**
 * Persisted descriptive and lifecycle fields of a webhook subscription.
 */
final readonly class RestoredWebhookSubscriptionMetadata
{
  public function __construct(
    public string $description,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
