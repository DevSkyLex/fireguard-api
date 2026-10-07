<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Reporting;

/**
 * Class MaintenanceEconomicAmount
 *
 * Keeps unknown contributions separate from the exact known subtotal.
 *
 * @category Contract
 */
final readonly class MaintenanceEconomicAmount
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param ?numeric-string $total complete exact total, or unknown
   * @param numeric-string $knownTotal exact known subtotal
   * @param bool $complete whether all retained contributions are valued
   * @param int $contributionCount retained physical or planning facts
   * @param int $unknownCount facts without a known valuation
   */
  public function __construct(public ?string $total, public string $knownTotal, public bool $complete, public int $contributionCount, public int $unknownCount)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method toArray
   *
   * @access public
   *
   * @return array{total:?string,knownTotal:string,complete:bool,contributionCount:int,unknownCount:int} exact transport values
   */
  public function toArray(): array
  {
    return ['total' => $this->total, 'knownTotal' => $this->knownTotal, 'complete' => $this->complete, 'contributionCount' => $this->contributionCount, 'unknownCount' => $this->unknownCount];
  }
  // #endregion
}
