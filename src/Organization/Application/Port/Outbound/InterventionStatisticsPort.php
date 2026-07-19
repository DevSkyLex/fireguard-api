<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

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
  // #endregion
}
