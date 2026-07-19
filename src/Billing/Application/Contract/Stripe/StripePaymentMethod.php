<?php

declare(strict_types=1);

namespace Billing\Application\Contract\Stripe;

/**
 * DTO StripePaymentMethod.
 *
 * Normalized, SDK-agnostic projection of a Stripe card payment method. The
 * gateway adapter extracts only the fields the read model needs (brand, last
 * 4 digits, expiry) so the application and presentation layers never see raw
 * card data, let alone collect or transmit it — Stripe is the sole source.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StripePaymentMethod
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StripePaymentMethod class.
   *
   * @since 1.0.0
   *
   * @param string $id the Stripe payment method identifier
   * @param string $brand the card brand (visa, mastercard, amex, ...)
   * @param string $last4 the last 4 digits of the card number
   * @param int $expMonth the card expiry month (1-12)
   * @param int $expYear the card expiry year
   */
  public function __construct(
    public string $id,
    public string $brand,
    public string $last4,
    public int $expMonth,
    public int $expYear,
  ) {
  }
  // #endregion
}
