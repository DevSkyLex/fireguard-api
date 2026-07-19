<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationPaymentMethod;

use Billing\Application\Contract\Stripe\StripePaymentMethod;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationPaymentMethodResult.
 *
 * Carries the organization's saved payment method, or null when the
 * organization has no Stripe customer yet, or the customer has no saved card
 * (e.g. an organization on the free plan).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationPaymentMethodResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationPaymentMethodResult class.
   *
   * @since 1.0.0
   *
   * @param ?StripePaymentMethod $paymentMethod the saved payment method, or null
   */
  public function __construct(
    public ?StripePaymentMethod $paymentMethod,
  ) {
  }
  // #endregion
}
