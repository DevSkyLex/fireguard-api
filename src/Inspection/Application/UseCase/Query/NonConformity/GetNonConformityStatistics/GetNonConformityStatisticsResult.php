<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformityStatistics;

use Inspection\Application\Contract\Statistics\{NonConformityEquipmentTypeCount, NonConformityStatisticsFacilityEntry};
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetNonConformityStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNonConformityStatisticsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, array{open: int, resolved: int}> $bySeverity open/resolved counts, all four severity keys always present
   * @param list<NonConformityStatisticsFacilityEntry> $byFacility top 10 facilities by open count, names resolved
   * @param list<NonConformityEquipmentTypeCount> $byEquipmentType top 10 equipment types by open count
   * @param ?float $averageResolutionDays mean resolution time in days, null when nothing resolved in the window
   * @param ?float $medianResolutionDays median resolution time in days, null when nothing resolved in the window
   * @param int $slaBreachedOpen unresolved non-conformities whose SLA breach has been stamped
   */
  public function __construct(
    public array $bySeverity,
    public array $byFacility,
    public array $byEquipmentType,
    public ?float $averageResolutionDays,
    public ?float $medianResolutionDays,
    public int $slaBreachedOpen,
  ) {
  }
  // #endregion
}
