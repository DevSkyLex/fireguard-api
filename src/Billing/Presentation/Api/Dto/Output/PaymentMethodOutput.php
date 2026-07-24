<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO PaymentMethodOutput.
 *
 * The organization's saved Stripe card, used to render the "payment method"
 * panel (brand, last 4 digits, expiry, and an "Update card" call to action
 * that redirects to the hosted Billing Portal). `hasPaymentMethod` is false
 * — with every other field null — when the organization has no saved card,
 * which is the normal state for a free-plan organization.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PaymentMethodOutput
{
  // #region Properties
  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $organizationId = '';

  /**
   * Property hasPaymentMethod.
   *
   * Whether the organization has a saved payment method.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $hasPaymentMethod = false;

  /**
   * Property brand.
   *
   * Card brand (visa, mastercard, amex, ...), or null when there is no saved
   * card.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $brand = null;

  /**
   * Property last4.
   *
   * Last 4 digits of the card number, or null when there is no saved card.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $last4 = null;

  /**
   * Property expMonth.
   *
   * Card expiry month (1-12), or null when there is no saved card.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $expMonth = null;

  /**
   * Property expYear.
   *
   * Card expiry year, or null when there is no saved card.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $expYear = null;
  // #endregion
}
