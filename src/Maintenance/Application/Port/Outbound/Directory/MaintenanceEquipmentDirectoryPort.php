<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Directory;

use Maintenance\Application\Contract\Directory\TrackableEquipment;

/**
 * Port MaintenanceEquipmentDirectoryPort.
 *
 * Cross-module read access to the equipment the maintenance sweep must
 * reconcile schedules for. Implemented by an Equipment-module adapter
 * (`Equipment\Infrastructure\Adapter\Maintenance\EquipmentMaintenanceDirectoryAdapter`),
 * mirroring how `EquipmentStatisticsAdapter` exposes Equipment data to the
 * Organization module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceEquipmentDirectoryPort
{
  // #region Methods
  /**
   * Method findEquipment.
   *
   * Finds a single published equipment record, for the defensive lazy
   * bootstrap path (an inspection closes on equipment the recurring sweep
   * has not reconciled into a schedule yet).
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the equipment identifier
   *
   * @return ?TrackableEquipment the equipment view, or null when not found
   */
  public function findEquipment(string $equipmentId): ?TrackableEquipment;

  /**
   * Method listEquipmentPage.
   *
   * Lists a page of published equipment across every organization, ordered
   * by id for stable pagination. Includes decommissioned equipment so the
   * sweep can detect and drop their schedules.
   *
   * @since 1.0.0
   *
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   *
   * @return list<TrackableEquipment> the equipment page
   */
  public function listEquipmentPage(int $limit, int $offset): array;
  // #endregion
}
