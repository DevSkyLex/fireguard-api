<?php

declare(strict_types=1);

namespace Procurement\Application\Contract\Reporting;

use RuntimeException;

/**
 * Class ProcurementEconomicOverviewUnavailable
 *
 * Refuses ambiguous currencies or an oversized selection instead of returning misleading totals.
 *
 * @category Contract
 */
final class ProcurementEconomicOverviewUnavailable extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $reason invalid_period, order_limit or mixed_currency
   * @param string $message safe reporting failure without supplier information
   *
   * @return void
   */
  public function __construct(public readonly string $reason, string $message)
  {
    parent::__construct($message);
  }
  // #endregion
}
