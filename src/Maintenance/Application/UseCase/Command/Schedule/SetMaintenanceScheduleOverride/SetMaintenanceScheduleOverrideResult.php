<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride;

use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase SetMaintenanceScheduleOverrideResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetMaintenanceScheduleOverrideResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleView $schedule the updated schedule view
   */
  public function __construct(public MaintenanceScheduleView $schedule)
  {
  }
}
