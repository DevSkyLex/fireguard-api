<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output\Statistics;

/**
 * DTO InterventionStatisticsOutput.
 *
 * Whole-organization intervention statistics: the list page's KPI cards and
 * the kanban's column counters. `byStatus` always carries all seven
 * `InterventionStatus` literals and `byPriority` all four `InterventionPriority`
 * literals, zeros included — stable keys the kanban can key off without
 * checking for absence.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionStatisticsOutput
{
  // #region Properties
  /**
   * Property total.
   *
   * Total intervention count for the organization, every status included.
   *
   * @since 1.0.0
   */
  public int $total = 0;

  /**
   * Property byStatus.
   *
   * Counts keyed by the exact `InterventionStatus` literal: `draft`,
   * `planned`, `in_progress`, `submitted`, `changes_requested`, `published`,
   * `abandoned`. All seven keys are always present.
   *
   * @since 1.0.0
   *
   * @var array<string,int>
   */
  public array $byStatus = [];

  /**
   * Property byPriority.
   *
   * Counts keyed by the exact `InterventionPriority` literal: `low`,
   * `normal`, `high`, `urgent`. All four keys are always present.
   *
   * @since 1.0.0
   *
   * @var array<string,int>
   */
  public array $byPriority = [];

  /**
   * Property overdue.
   *
   * Count of non-terminal interventions (excludes `published`/`abandoned`)
   * whose `dueAt` is in the past.
   *
   * @since 1.0.0
   */
  public int $overdue = 0;

  /**
   * Property dueSoon.
   *
   * Count of interventions in a still-active status (`planned`, `in_progress`,
   * `changes_requested`) whose `dueAt` falls within the next 48 hours —
   * matching the due-date reminder sweep's own window.
   *
   * @since 1.0.0
   */
  public int $dueSoon = 0;

  /**
   * Property bySite.
   *
   * The top 10 sites by intervention count, descending. Bounded, not an
   * exhaustive per-site breakdown.
   *
   * @since 1.0.0
   *
   * @var list<InterventionSiteStatisticOutput>
   */
  public array $bySite = [];

  /**
   * Property byResponsible.
   *
   * The top 10 responsible members by intervention count, descending.
   * Bounded, not an exhaustive per-member breakdown.
   *
   * @since 1.0.0
   *
   * @var list<InterventionResponsibleStatisticOutput>
   */
  public array $byResponsible = [];

  /**
   * Property averagePublicationDays.
   *
   * Mean number of days between an intervention's draft creation and its
   * publication, across every published intervention in the organization.
   * `null` when the organization has none.
   *
   * @since 1.0.0
   */
  public ?float $averagePublicationDays = null;
  // #endregion
}
