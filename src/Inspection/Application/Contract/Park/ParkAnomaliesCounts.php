<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Park;

/**
 * Contract ParkAnomaliesCounts.
 *
 * Counts unresolved findings across the complete resolved equipment scope.
 *
 * @category Contract
 */
final readonly class ParkAnomaliesCounts
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param int $openAnomalies total unresolved findings
   * @param array{low: int, medium: int, high: int, critical: int} $bySeverity findings by severity, including zeros
   *
   * @return void
   */
  public function __construct(
    public int $openAnomalies,
    public array $bySeverity = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0],
  ) {
  }
  // #endregion
}
