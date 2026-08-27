<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Naming;

/**
 * Port MaintenanceEquipmentNamingPort.
 *
 * Resolves equipment identifiers into the serial number printed on the
 * device, so a maintenance schedule export can name what it tracks — mirrors
 * `Inspection\Application\Port\Outbound\EquipmentNamingPort`.
 *
 * Separate from {@see \Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort}:
 * that one answers "which equipment must the sweep reconcile" and is called
 * page by page across every organization; this one is a bulk, read-only
 * label lookup scoped to a batch of identifiers already known.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceEquipmentNamingPort
{
  // #region Methods
  /**
   * Method findSerialNumbersByIds.
   *
   * Resolves the serial number of each given equipment, in one round trip.
   * Equipment with no recorded serial number, and identifiers that cannot be
   * resolved, are absent from the result rather than mapped to an empty
   * string — an unknown serial is not a blank one.
   *
   * @since 1.0.0
   *
   * @param list<string> $equipmentIds the equipment identifiers to resolve
   *
   * @return array<string, string> serial number keyed by equipment identifier
   */
  public function findSerialNumbersByIds(array $equipmentIds): array;
  // #endregion
}
