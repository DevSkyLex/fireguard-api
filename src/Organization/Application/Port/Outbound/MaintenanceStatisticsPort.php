<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use DateTimeImmutable;
use Organization\Application\Contract\Maintenance\MaintenanceDueSummary;

/**
 * Port MaintenanceStatisticsPort.
 *
 * Cross-module read access to the Maintenance module's schedule deadlines,
 * scoped to one organization. Backs the weekly digest's maintenance section;
 * implemented by `Maintenance\Infrastructure\Adapter\Organization\MaintenanceStatisticsAdapter`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceStatisticsPort
{
  // #region Methods
  /**
   * Method countDueOverview.
   *
   * Counts the organization's maintenance deadlines against the given window:
   * `overdue` deadlines are strictly before `$now`, `due_soon` deadlines fall
   * inside `[$now, $windowEnd]`. Both are computed from the schedules'
   * `next_due_at`, independently of the hourly sweep's precomputed status.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $now the current instant
   * @param DateTimeImmutable $windowEnd the upper bound of the "due soon" window
   *
   * @return array{due_soon: int, overdue: int} the deadline counts
   */
  public function countDueOverview(string $organizationId, DateTimeImmutable $now, DateTimeImmutable $windowEnd): array;

  /**
   * Method findDueSchedules.
   *
   * Lists the organization's maintenance deadlines due before `$windowEnd`
   * (overdue ones included), soonest first. Backs the weekly digest detail
   * lines.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $now the current instant
   * @param DateTimeImmutable $windowEnd the upper bound of the window
   * @param int $limit maximum number of summaries to return
   *
   * @return list<MaintenanceDueSummary> the due maintenance summaries
   */
  public function findDueSchedules(string $organizationId, DateTimeImmutable $now, DateTimeImmutable $windowEnd, int $limit): array;
  // #endregion
}
