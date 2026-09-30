<?php

declare(strict_types=1);

namespace Billing\Domain\Model\Subscription;

use Billing\Domain\ValueObject\{BillingInterval, SubscriptionStatus};
use DateTimeImmutable;

/** Persisted subscription lifecycle and billing projection. */
final readonly class RestoredSubscriptionState
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores the subscription status, selected plan, billing interval, and current-period cancellation state.
   *
   * @access public
   *
   * @param SubscriptionStatus $status current lifecycle status of the subscription
   * @param ?string $stripeSubscriptionId linked Stripe subscription identifier, when present
   * @param ?string $planKey selected organization plan key, when present
   * @param ?BillingInterval $interval billing interval, when configured
   * @param ?DateTimeImmutable $currentPeriodEnd end of the current billing period, when known
   * @param bool $cancelAtPeriodEnd whether the subscription is scheduled to end with this period
   *
   * @return void
   */
  public function __construct(
    public SubscriptionStatus $status,
    public ?string $stripeSubscriptionId = null,
    public ?string $planKey = null,
    public ?BillingInterval $interval = null,
    public ?DateTimeImmutable $currentPeriodEnd = null,
    public bool $cancelAtPeriodEnd = false,
  ) {
  }
  // #endregion
}
