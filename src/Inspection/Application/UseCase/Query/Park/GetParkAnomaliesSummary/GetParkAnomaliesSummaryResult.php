<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Park\GetParkAnomaliesSummary;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetParkAnomaliesSummaryResult.
 *
 * Separates unresolved finding counts from equipment state and preventive deadlines.
 *
 * @category UseCase
 */
final readonly class GetParkAnomaliesSummaryResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param int $openAnomalies all unresolved findings
   * @param array{low: int, medium: int, high: int, critical: int} $bySeverity complete severity buckets
   *
   * @return void
   */
  public function __construct(
    public int $openAnomalies,
    public array $bySeverity,
  ) {
  }
  // #endregion
}
