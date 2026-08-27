<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use DateTimeImmutable;
use Intervention\Application\Contract\Statistics\InterventionStatisticsAggregate;

/**
 * Interface InterventionStatisticsGatewayPort.
 *
 * Module-internal statistics surface, feeding the list page's KPI cards and
 * the kanban's column counters. Distinct from
 * `Organization\Application\Port\Outbound\InterventionStatisticsPort`, which
 * is a cross-module contract Organization consumes for its dashboard
 * (`findRecentInterventions`, `countOverview`): that one is owned by
 * Organization and shaped for Organization's needs. This one is owned by
 * Intervention itself and feeds Intervention's own `/interventions/statistics`
 * endpoint. Both are implemented against the same `InterventionRecord` table
 * by adapters that mirror each other's querying approach (single grouped
 * queries, never N+1), without either port depending on the other.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionStatisticsGatewayPort
{
  // #region Methods
  /**
   * Method aggregate.
   *
   * Computes the whole-organization statistics snapshot in a bounded number
   * of grouped queries.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $now the instant to measure `overdue`/`dueSoon` against
   *
   * @return InterventionStatisticsAggregate the raw, unresolved statistics aggregate
   */
  public function aggregate(string $organizationId, DateTimeImmutable $now): InterventionStatisticsAggregate;
  // #endregion
}
