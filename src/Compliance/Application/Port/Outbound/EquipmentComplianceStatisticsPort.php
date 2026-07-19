<?php

declare(strict_types=1);

namespace Compliance\Application\Port\Outbound;

/**
 * Port EquipmentComplianceStatisticsPort.
 *
 * Reads equipment inventory counts grouped by facility, including equipment
 * types not yet tracked by a Maintenance schedule row. Implemented by an
 * adapter hosted in the Equipment module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentComplianceStatisticsPort
{
  // #region Methods
  /**
   * Method equipmentInventoryByFacility.
   *
   * Counts published equipment grouped by facility. Equipment with no
   * assigned facility buckets under the `unassigned` key.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, array{total: int, active: int}> map of facilityId (or `unassigned`) => inventory counts
   */
  public function equipmentInventoryByFacility(string $organizationId): array;
  // #endregion
}
