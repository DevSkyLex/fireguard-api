<?php

declare(strict_types=1);

namespace Billing\Application\Contract\Stripe;

/**
 * DTO StripeEvent.
 *
 * Normalized, SDK-agnostic projection of a verified Stripe webhook event. The
 * gateway adapter extracts the fields the application needs from the raw Stripe
 * payload so the use cases stay decoupled from the Stripe SDK.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StripeEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StripeEvent class.
   *
   * @since 1.0.0
   *
   * @param string $type the Stripe event type (e.g. customer.subscription.updated)
   * @param ?string $organizationId the organization identifier read from metadata
   * @param ?string $customerId the Stripe customer identifier
   * @param ?string $subscriptionId the Stripe subscription identifier
   * @param ?string $status the raw Stripe subscription status
   * @param ?string $priceId the Stripe price identifier of the first line item
   * @param ?int $currentPeriodEnd the current period end as a UNIX timestamp
   * @param bool $cancelAtPeriodEnd whether cancellation is scheduled at period end
   */
  public function __construct(
    public string $type,
    public ?string $organizationId = null,
    public ?string $customerId = null,
    public ?string $subscriptionId = null,
    public ?string $status = null,
    public ?string $priceId = null,
    public ?int $currentPeriodEnd = null,
    public bool $cancelAtPeriodEnd = false,
  ) {
  }
  // #endregion
}
