<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Application\Contract\Inspection\OpenNonConformitySummary;

/**
 * Port NonConformityStatisticsPort.
 *
 * Exposes non-conformity KPI aggregates to the Organization module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NonConformityStatisticsPort
{
  /**
   * Counts non-conformities for an organization with optional severity/status filters.
   */
  public function countNonConformities(string $organizationId, ?string $severity = null, ?string $status = null): int;

  /**
   * Returns aggregate non-conformity overview counts for dashboard cards and alerts.
   *
   * @return array{total: int, open: int, in_progress: int, done: int, waived: int, overdue: int, critical_open: int}
   */
  public function countNonConformityOverview(
    string $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Returns non-conformity counts grouped by status.
   *
   * @return array<string, int> map of status => count
   */
  public function countNonConformitiesByStatus(string $organizationId): array;

  /**
   * Returns non-conformity counts grouped by severity.
   *
   * @return array<string, int> map of severity => count
   */
  public function countNonConformitiesBySeverity(string $organizationId): array;

  /**
   * Counts overdue open non-conformities for an organization.
   */
  public function countOverdueNonConformities(
    string $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): int;

  /**
   * Counts non-conformities that were active at a given instant.
   */
  public function countActiveNonConformitiesAtDate(
    string $organizationId,
    string $at,
    ?string $severity = null,
    ?string $status = null,
  ): int;

  /**
   * Returns aggregate non-conformity counts for a bounded dashboard period.
   *
   * @return array{opened: int, resolved: int, activeAtStart: int}
   */
  public function countNonConformityPeriodMetrics(
    string $organizationId,
    string $periodFrom,
    string $periodTo,
    string $activeAt,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Returns non-conformity creation counts grouped by day for a period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countNonConformitiesCreatedByDay(
    string $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Returns non-conformity resolution counts grouped by day for a period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countNonConformitiesResolvedByDay(
    string $organizationId,
    string $resolvedAtFrom,
    string $resolvedAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array;

  /**
   * Counts critical non-conformities that are still open or in progress.
   */
  public function countOpenCriticalNonConformities(string $organizationId, ?string $status = null): int;

  /**
   * Method countSlaBreachedNonConformities.
   *
   * Counts the organization's unresolved non-conformities whose resolution
   * SLA breach has been signalled (the hourly SLA sweep stamped them).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the unresolved SLA-breached non-conformity count
   */
  public function countSlaBreachedNonConformities(string $organizationId): int;

  /**
   * Method findOpenNonConformities.
   *
   * Lists the organization's unresolved non-conformities, oldest first.
   * Backs the weekly digest detail lines.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param int $limit maximum number of summaries to return
   *
   * @return list<OpenNonConformitySummary> the unresolved non-conformity summaries
   */
  public function findOpenNonConformities(string $organizationId, int $limit): array;
}
