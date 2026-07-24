<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use DateTimeImmutable;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;

/**
 * Port InterventionStatisticsPort.
 *
 * Provides intervention statistics for organization dashboards without
 * coupling the Organization module to the Intervention repository.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionStatisticsPort
{
  // #region Methods
  /**
   * Method findRecentInterventions.
   *
   * Returns the most recently updated interventions for an organization,
   * across every status, ordered by `updatedAt` descending.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param int $limit the maximum number of interventions to return
   *
   * @return list<RecentInterventionSummary> the recent intervention summaries
   */
  public function findRecentInterventions(string $organizationId, int $limit): array;

  /**
   * Method countOverview.
   *
   * Counts an organization's interventions for the dashboard headline: the
   * total, the ones still in flight, and the ones past their due date.
   *
   * "Open" excludes `published` and `abandoned` — the two end states. An
   * intervention that is over is not work in progress.
   *
   * "Overdue" counts only open interventions whose `dueAt` is in the past. A
   * published intervention that finished late is history, not a thing to act
   * on, and counting it would make the figure grow forever.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $now the instant to measure lateness against
   *
   * @return array{total: int, open: int, overdue: int} the overview counts
   */
  public function countOverview(string $organizationId, DateTimeImmutable $now): array;
  // #endregion
}
