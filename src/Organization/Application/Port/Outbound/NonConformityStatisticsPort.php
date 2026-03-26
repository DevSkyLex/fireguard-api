<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

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
   * Counts all non-conformities for an organization.
   */
  public function countNonConformities(string $organizationId): int;

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
}
