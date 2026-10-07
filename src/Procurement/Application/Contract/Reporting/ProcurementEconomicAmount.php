<?php

declare(strict_types=1);

namespace Procurement\Application\Contract\Reporting;

/**
 * Class ProcurementEconomicAmount
 *
 * Preserves a known subtotal when missing purchase prices prevent a complete amount.
 *
 * @category Contract
 */
final readonly class ProcurementEconomicAmount
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ?string $total exact six-place amount, absent when incomplete
   * @param string $knownTotal exact six-place subtotal of known prices
   * @param bool $complete whether every positive quantity has a known price
   *
   * @return void
   */
  public function __construct(public ?string $total, public string $knownTotal, public bool $complete)
  {
  }
  // #endregion
}
