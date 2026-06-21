<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO InvoiceOutput.
 *
 * A single billing invoice of an organization, used to render the in-app billing
 * history (date, amount, status, and links to the hosted invoice and PDF).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvoiceOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * Stripe invoice identifier.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property number.
   *
   * Human-readable invoice number, when issued.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $number = null;

  /**
   * Property status.
   *
   * Invoice status (paid, open, void, draft, uncollectible).
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property amount.
   *
   * Billed amount in the currency's minor unit (amount paid, falling back to
   * amount due for unpaid invoices).
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $amount = 0;

  /**
   * Property currency.
   *
   * ISO 4217 currency code.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $currency = '';

  /**
   * Property createdAt.
   *
   * ISO 8601 timestamp of the invoice creation date, or null.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $createdAt = null;

  /**
   * Property hostedInvoiceUrl.
   *
   * Stripe-hosted invoice page URL, or null.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $hostedInvoiceUrl = null;

  /**
   * Property invoicePdf.
   *
   * Downloadable invoice PDF URL, or null.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $invoicePdf = null;
  // #endregion
}
