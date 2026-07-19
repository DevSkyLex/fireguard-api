<?php

declare(strict_types=1);

namespace Billing\Application\Contract\Stripe;

use DateTimeImmutable;

/**
 * DTO StripeInvoice.
 *
 * Normalized, SDK-agnostic projection of a Stripe invoice. The gateway adapter
 * extracts the fields the billing history needs from the raw Stripe invoice so
 * the application and presentation layers stay decoupled from the Stripe SDK.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StripeInvoice
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StripeInvoice class.
   *
   * @since 1.0.0
   *
   * @param string $id the Stripe invoice identifier
   * @param ?string $number the human-readable invoice number
   * @param string $status the invoice status (paid, open, void, draft, uncollectible)
   * @param int $amountPaid the amount paid in the currency's minor unit
   * @param int $amountDue the amount due in the currency's minor unit
   * @param string $currency the ISO 4217 currency code
   * @param ?DateTimeImmutable $createdAt the invoice creation date
   * @param ?string $hostedInvoiceUrl the Stripe-hosted invoice page URL
   * @param ?string $invoicePdf the downloadable PDF URL
   */
  public function __construct(
    public string $id,
    public ?string $number,
    public string $status,
    public int $amountPaid,
    public int $amountDue,
    public string $currency,
    public ?DateTimeImmutable $createdAt,
    public ?string $hostedInvoiceUrl,
    public ?string $invoicePdf,
  ) {
  }
  // #endregion
}
