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
   * Counts all inspections for an organization.
   */
  public function countInspections(string $organizationId): int;

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
}
