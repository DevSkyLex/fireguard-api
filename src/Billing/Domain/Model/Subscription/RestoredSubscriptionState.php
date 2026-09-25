<?php

declare(strict_types=1);

namespace Billing\Domain\Model\Subscription;

use Billing\Domain\ValueObject\{BillingInterval, SubscriptionStatus};
use DateTimeImmutable;

/** Persisted subscription lifecycle and billing projection. */
final readonly class RestoredSubscriptionState
{
  public function __construct(
    public SubscriptionStatus $status,
    public ?string $stripeSubscriptionId = null,
    public ?string $planKey = null,
    public ?BillingInterval $interval = null,
    public ?DateTimeImmutable $currentPeriodEnd = null,
    public bool $cancelAtPeriodEnd = false,
  ) {
  }
}
