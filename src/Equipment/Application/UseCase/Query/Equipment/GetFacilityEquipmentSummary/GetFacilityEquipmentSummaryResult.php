<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary;

use Shared\Application\Message\ResultMessage;

/**
 * Result GetFacilityEquipmentSummaryResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityEquipmentSummaryResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @since 1.0.0
   *
   * @param string $scope the direct or subtree scope
   * @param int $totalItems total published equipment before pagination
   * @param array{in_stock: int, operational: int, under_maintenance: int, decommissioned: int} $byStatus the complete status buckets
   * @param int $needingAttentionCount equipment under maintenance or decommissioned
   */
  public function __construct(public string $scope, public int $totalItems, public array $byStatus, public int $needingAttentionCount)
  {
  }
  // #endregion
}
