<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Inbound;

/**
 * Port EquipmentMaintenanceLogSynchronizerPort.
 *
 * Inbound contract used by the flat equipment write surfaces (canonical mutation
 * processor, intervention publication adapter) to keep the maintenance-log history
 * in step with a status transition without duplicating the log open/close rules.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentMaintenanceLogSynchronizerPort
{
  // #region Methods
  /**
   * Method syncForStatusTransition.
   *
   * Opens a maintenance log when the equipment enters `under_maintenance` and
   * closes the open one when it leaves maintenance (to operational, in stock, or
   * decommissioned). A no-op when the status is unchanged or the transition does
   * not enter or leave maintenance.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the equipment identifier
   * @param string $organizationId the organization identifier
   * @param string $previousStatus the status before the change
   * @param string $newStatus the status after the change
   */
  public function syncForStatusTransition(
    string $equipmentId,
    string $organizationId,
    string $previousStatus,
    string $newStatus,
  ): void;
  // #endregion
}
