<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules;

use Maintenance\Application\Contract\Schedule\MaintenanceSchedulePage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListMaintenanceSchedulesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMaintenanceSchedulesResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceSchedulePage $page the page value
   */
  public function __construct(public MaintenanceSchedulePage $page)
  {
  }
}
