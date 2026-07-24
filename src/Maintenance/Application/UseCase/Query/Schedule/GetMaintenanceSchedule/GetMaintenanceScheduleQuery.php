<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetMaintenanceScheduleQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetMaintenanceScheduleQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $scheduleId the maintenance schedule id value
   */
  public function __construct(
    public string $userId,
    public string $scheduleId,
  ) {
  }
}
