<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO PlanPricingOutput.
 *
 * Display pricing for a payable plan. Amounts are integers in the currency's
 * smallest unit (e.g. cents); a null amount means the cadence is not offered.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanPricingOutput
{
  // #region Properties
  /**
   * Property planKey.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $planKey = '';

  /**
   * Property currency.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $currency = '';

  /**
   * Property monthlyAmount.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $monthlyAmount = null;

  /**
   * Property yearlyAmount.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $yearlyAmount = null;
  // #endregion
}
