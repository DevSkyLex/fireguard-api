<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Presentation\Api\Dto\Output\PlanPricingOutput;

/**
 * Provider GetPricingProvider.
 *
 * Exposes the configured display pricing for every payable plan.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PlanPricingOutput>
 */
final readonly class GetPricingProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPricingProvider class.
   *
   * @since 1.0.0
   *
   * @param BillingPriceCatalog $priceCatalog the plan price catalog
   */
  public function __construct(
    private BillingPriceCatalog $priceCatalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<PlanPricingOutput> the pricing collection
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $outputs = [];

    foreach ($this->priceCatalog->pricing() as $pricing) {
      $output = new PlanPricingOutput();
      $output->planKey = $pricing->planKey;
      $output->currency = $pricing->currency;
      $output->monthlyAmount = $pricing->monthlyAmount;
      $output->yearlyAmount = $pricing->yearlyAmount;
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}
