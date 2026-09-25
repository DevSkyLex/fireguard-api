<?php

declare(strict_types=1);

namespace Webhook\Domain\Model\Delivery;

use DateTimeImmutable;

/** Event data and timestamps as stored for a webhook delivery. */
final readonly class RestoredWebhookDeliveryMetadata
{
  /**
   * @param array<string, mixed> $payload
   */
  public function __construct(
    public string $eventType,
    public string $eventId,
    public array $payload,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
