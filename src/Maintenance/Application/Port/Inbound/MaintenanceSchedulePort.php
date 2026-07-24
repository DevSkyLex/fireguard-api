<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Inbound;

use DateTimeImmutable;

/**
 * Port MaintenanceSchedulePort.
 *
 * Inbound port other modules use to synchronize maintenance schedules on
 * significant events. Today's single trigger is an inspection closing
 * (consumed by the Inspection module through
 * `Inspection\Application\Port\Outbound\InspectionMaintenanceSynchronizerPort`,
 * whose adapter lives in this module and delegates here), but the seam is
 * kept generic so future triggers (e.g. equipment recommissioning) can reuse
 * it.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceSchedulePort
{
  // #region Methods
  /**
   * Method onInspectionClosed.
   *
   * Recomputes the maintenance schedule for one piece of equipment after an
   * inspection closes: `lastInspectionClosedAt` is set to `closedAt`,
   * `nextDueAt` is recomputed from the effective interval (per-schedule
   * override, else the organization's compliance periodicity for the
   * equipment type, else untracked), `dueStatus` is recomputed accordingly,
   * and the anti-duplicate reminder marker is reset when the due date
   * changed. Best-effort: implementations must never throw for a
   * best-effort caller to swallow safely, but SHOULD still surface
   * programming errors via logging.
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
