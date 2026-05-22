<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port EquipmentStatisticsPort.
 *
 * Exposes equipment KPI aggregates to the Organization module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentStatisticsPort
{
  /**
   * Counts equipment for an organization with optional type/status filters.
   *
   * @param string $organizationId the organization identifier
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   *
   * @return int the total equipment count
   */
  public function countEquipment(string $organizationId, ?string $type = null, ?string $status = null): int;

  /**
   * Returns aggregate equipment overview counts for dashboard cards.
   *
   * @return array{total: int, in_stock: int, operational: int, under_maintenance: int, decommissioned: int}
   */
  public function countEquipmentOverview(string $organizationId, ?string $type = null, ?string $status = null): array;

  /**
   * Returns equipment counts grouped by status.
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, int> map of status => count
   */
  public function countEquipmentByStatus(string $organizationId): array;

  /**
   * Returns equipment counts grouped by type.
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, int> map of type => count
   */
  public function countEquipmentByType(string $organizationId): array;

  /**
   * Returns equipment creation counts grouped by day for a period.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countEquipmentCreatedByDay(
    string $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
    ?string $status = null,
  ): array;
}
