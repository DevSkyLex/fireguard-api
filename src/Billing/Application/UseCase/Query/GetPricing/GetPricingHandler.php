<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetPricing;

use Billing\Application\Service\BillingPriceCatalog;
use Shared\Application\Message\QueryHandler;

/**
 * Handler GetPricingHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPricingHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param BillingPriceCatalog $catalog the owner's configured price catalog
   */
  public function __construct(private BillingPriceCatalog $catalog)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetPricingQuery $query the pricing query
   *
   * @return GetPricingResult the immutable catalog projection
   */
  public function __invoke(GetPricingQuery $query): GetPricingResult
  {
    return new GetPricingResult($this->catalog->pricing());
  }
  // #endregion
}
