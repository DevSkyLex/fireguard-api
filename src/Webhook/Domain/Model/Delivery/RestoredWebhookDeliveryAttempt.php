<?php

declare(strict_types=1);

namespace Webhook\Domain\Model\Delivery;

use DateTimeImmutable;
use Webhook\Domain\ValueObject\WebhookDeliveryStatus;

/** Last persisted attempt and lifecycle state, including historical partial failures. */
final readonly class RestoredWebhookDeliveryAttempt
{
  public function __construct(
    public WebhookDeliveryStatus $status,
    public int $attempts,
    public ?int $httpStatus,
    public ?string $lastError,
    public ?DateTimeImmutable $nextRetryAt,
    public ?DateTimeImmutable $deliveredAt,
  ) {
  }
}
