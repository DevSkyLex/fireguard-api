<?php

declare(strict_types=1);

namespace Billing\Application\Contract\Stripe;

/**
 * Current Stripe subscription, retrieved independently of a webhook snapshot.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StripeSubscription
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Projects the Stripe subscription fields consumed by Billing, including its current period and cancellation state.
   *
   * @access public
   *
   * @param string $id Stripe subscription identifier
   * @param string $customerId Stripe customer owning the subscription
   * @param ?string $organizationId linked organization identifier, when resolved
   * @param string $status subscription status reported by Stripe
   * @param ?string $priceId Stripe price identifier for the subscription, when present
   * @param ?int $currentPeriodEnd Unix timestamp ending the current billing period, when available
   * @param bool $cancelAtPeriodEnd whether cancellation is scheduled for period end
   * @param int $created Unix timestamp when Stripe created the subscription
   * @param bool $liveMode whether Stripe created this object in live mode
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $customerId,
    public ?string $organizationId,
    public string $status,
    public ?string $priceId,
    public ?int $currentPeriodEnd,
    public bool $cancelAtPeriodEnd,
    public int $created,
    public bool $liveMode,
  ) {
  }
  // #endregion
}
