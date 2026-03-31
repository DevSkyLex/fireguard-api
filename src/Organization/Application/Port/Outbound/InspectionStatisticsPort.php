<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port InspectionStatisticsPort.
 *
 * Exposes inspection KPI aggregates to the Organization module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InspectionStatisticsPort
{
  /**
   * Counts inspections for an organization with optional filters.
   */
  public function countInspections(
    string $organizationId,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): int;

  /**
   * Returns inspection counts grouped by status.
   *
   * @return array<string, int> map of status => count
   */
  public function countInspectionsByStatus(string $organizationId): array;

  /**
   * Returns inspection counts grouped by result.
   *
   * @return array<string, int> map of result => count
   */
  public function countInspectionsByResult(string $organizationId): array;

  /**
   * Returns inspection counts grouped by inspector type.
   *
   * @return array<string, int> map of inspector type => count
   */
  public function countInspectionsByInspectorType(string $organizationId): array;

  /**
   * Counts inspections performed from a given lower bound.
   */
  public function countInspectionsPerformedSince(string $organizationId, string $performedAtFrom): int;

  /**
   * Counts inspections for a bounded period with optional filters.
   */
  public function countInspectionsBetween(
    string $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): int;

  /**
   * Returns inspection counts grouped by performed day for a period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countInspectionsPerformedByDay(
    string $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $timeZone = null,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array;
}
