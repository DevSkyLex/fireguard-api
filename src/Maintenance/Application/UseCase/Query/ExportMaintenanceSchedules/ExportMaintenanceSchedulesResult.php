<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules;

use Maintenance\Application\Contract\Export\MaintenanceScheduleExportRow;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportMaintenanceSchedulesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportMaintenanceSchedulesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<MaintenanceScheduleExportRow> $rows the bounded, name-resolved export rows
   * @param int $total the total number of matching schedules
   */
  public function __construct(
    public array $rows,
    public int $total,
  ) {
  }
  // #endregion
}
