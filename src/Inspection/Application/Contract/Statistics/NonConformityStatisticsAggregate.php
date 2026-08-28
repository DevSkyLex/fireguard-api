<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Statistics;

/**
 * Contract NonConformityStatisticsAggregate.
 *
 * The raw, name-less snapshot the statistics gateway computes in a bounded
 * number of grouped queries. The handler zero-fills the severity keys and
 * resolves facility names.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityStatisticsAggregate
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, NonConformitySeverityBucket> $bySeverity open/resolved counts keyed by severity (absent = zero)
   * @param list<NonConformityFacilityCount> $topFacilities top facilities by open count, descending
   * @param list<NonConformityEquipmentTypeCount> $topEquipmentTypes top equipment types by open count, descending
   * @param ?float $averageResolutionDays mean of (resolvedAt - createdAt) in days over resolved rows, null when none
   * @param ?float $medianResolutionDays median of the same distribution, null when none
   * @param int $slaBreachedOpen unresolved rows whose SLA breach has been stamped
   */
  public function __construct(
    public array $bySeverity,
    public array $topFacilities,
    public array $topEquipmentTypes,
    public ?float $averageResolutionDays,
    public ?float $medianResolutionDays,
    public int $slaBreachedOpen,
  ) {
  }
  // #endregion
}
