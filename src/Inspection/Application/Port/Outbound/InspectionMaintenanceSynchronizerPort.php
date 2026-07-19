<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use DateTimeImmutable;

/**
 * Port InspectionMaintenanceSynchronizerPort.
 *
 * Outbound seam the Inspection module uses to notify the Maintenance module
 * that an inspection closed, so the equipment's maintenance schedule can be
 * recomputed. Implemented by an adapter living in the Maintenance module
 * (`Maintenance\Infrastructure\Adapter\Inspection\MaintenanceScheduleSynchronizerAdapter`),
 * which delegates to `Maintenance\Application\Port\Inbound\MaintenanceSchedulePort`
 * — the same cross-module shape as `InterventionEquipmentDraftProviderPort`
 * consumed from Intervention and implemented by an Equipment adapter.
 *
 * Called by `CloseInspectionHandler` AFTER the durable commit, wrapped in a
 * try/catch: maintenance synchronization is best-effort and must never fail
 * inspection closing.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InspectionMaintenanceSynchronizerPort
{
  // #region Methods
  /**
   * Method onInspectionClosed.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   * @param DateTimeImmutable $closedAt the inspection closure instant
   */
  public function onInspectionClosed(string $organizationId, string $equipmentId, DateTimeImmutable $closedAt): void;
  // #endregion
}
