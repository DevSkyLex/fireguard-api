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
   * Counts all equipment for an organization.
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the total equipment count
   */
  public function countEquipment(string $organizationId): int;

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
}
