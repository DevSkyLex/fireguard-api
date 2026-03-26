<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationFacilityStatistics;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationFacilityStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationFacilityStatisticsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationFacilityStatisticsResult class.
   *
   * @since 1.0.0
   *
   * @param int $totalCount the total number of facilities
   * @param int $activeCount the total number of active facilities
   * @param int $archivedCount the total number of archived facilities
   * @param array<string, int> $countsByType map of facility type => count
   */
  public function __construct(
    public int $totalCount,
    public int $activeCount,
    public int $archivedCount,
    public array $countsByType,
  ) {
  }
  // #endregion
}
