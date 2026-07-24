<?php

declare(strict_types=1);

namespace Billing\Application\Service;

/**
 * DTO PlanPricing.
 *
 * Display pricing for a single payable plan, exposed to the frontend so plan
 * cards can show amounts. Amounts are integers in the currency's smallest unit
 * (e.g. cents), mirroring Stripe's representation. A null amount means the
 * matching cadence is not offered.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanPricing
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PlanPricing class.
   *
   * @since 1.0.0
   *
   * @param string $planKey the plan key (e.g. pro, max)
   * @param string $currency the ISO currency code
   * @param ?int $monthlyAmount the monthly amount in the smallest currency unit
   * @param ?int $yearlyAmount the yearly amount in the smallest currency unit
   */
  public function __construct(
    public string $planKey,
    public string $currency,
    public ?int $monthlyAmount,
    public ?int $yearlyAmount,
  ) {
  }
  // #endregion
}
