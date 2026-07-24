<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Inspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionMaintenanceSynchronizerPort;
use Maintenance\Application\Port\Inbound\MaintenanceSchedulePort;

/**
 * Adapter MaintenanceScheduleSynchronizerAdapter.
 *
 * Implements the Inspection module's maintenance synchronizer port by
 * delegating to this module's own inbound {@see MaintenanceSchedulePort}.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleSynchronizerAdapter implements InspectionMaintenanceSynchronizerPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceSchedulePort $schedulePort the maintenance schedule inbound port
   */
  public function __construct(
    private MaintenanceSchedulePort $schedulePort,
  ) {
  }
  // #endregion

  // #region Methods
  public function onInspectionClosed(string $organizationId, string $equipmentId, DateTimeImmutable $closedAt): void
  {
    $this->schedulePort->onInspectionClosed($organizationId, $equipmentId, $closedAt);
  }
  // #endregion
}
