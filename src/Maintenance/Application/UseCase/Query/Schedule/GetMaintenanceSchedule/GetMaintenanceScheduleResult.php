<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule;

use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetMaintenanceScheduleResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetMaintenanceScheduleResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleView $schedule the schedule view
   */
  public function __construct(public MaintenanceScheduleView $schedule)
  {
  }
}
