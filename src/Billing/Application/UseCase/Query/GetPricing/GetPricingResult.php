<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetPricing;

use Billing\Application\Contract\Pricing\PlanPricing;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetPricingResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPricingResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<PlanPricing> $plans the configured payable plans
   */
  public function __construct(public array $plans)
  {
  }
  // #endregion
}
