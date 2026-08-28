<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Statistics;

/**
 * DTO NonConformityStatisticsOutput.
 *
 * Organization-wide non-conformity statistics. "Open" always means status
 * `open` or `in_progress`; "resolved" means `done` or `waived`.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityStatisticsOutput
{
  // #region Properties
  /**
   * Property bySeverity.
   *
   * Open/resolved counts keyed by the exact `NonConformitySeverity`
   * literal: `low`, `medium`, `high`, `critical`. All four keys are always
   * present, zeros included.
   *
   * @since 1.0.0
   *
   * @var array<string, array{open: int, resolved: int}>
   */
  public array $bySeverity = [];

  /**
   * Property byFacility.
   *
   * Top 10 facilities by open non-conformity count, descending.
   *
   * @since 1.0.0
   *
   * @var list<NonConformityFacilityStatisticOutput>
   */
  public array $byFacility = [];

  /**
   * Property byEquipmentType.
   *
   * Top 10 equipment types by open non-conformity count, descending.
   *
   * @since 1.0.0
   *
   * @var list<NonConformityEquipmentTypeStatisticOutput>
   */
  public array $byEquipmentType = [];

  /**
   * Property resolution.
   *
   * Resolution time metrics over the resolved rows in the window:
   * `averageDays` (mean) and `medianDays` (PERCENTILE_CONT 0.5), both in
   * fractional days over `resolvedAt - createdAt`, both null when nothing
   * resolved in the window.
   *
   * @since 1.0.0
   *
   * @var array{averageDays: ?float, medianDays: ?float}
   */
  public array $resolution = ['averageDays' => null, 'medianDays' => null];

  /**
   * Property slaBreachedOpen.
   *
   * Unresolved non-conformities whose resolution SLA breach has been
   * stamped by the hourly SLA sweep.
   *
   * @since 1.0.0
   */
  public int $slaBreachedOpen = 0;
  // #endregion
}
