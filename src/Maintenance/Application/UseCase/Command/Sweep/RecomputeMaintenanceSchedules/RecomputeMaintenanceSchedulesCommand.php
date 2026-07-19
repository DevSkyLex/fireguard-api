<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RecomputeMaintenanceSchedulesCommand.
 *
 * Triggered hourly by {@see \Maintenance\Infrastructure\Scheduler\MaintenanceScheduleProvider}.
 * Carries no payload: the sweep always processes every organization.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecomputeMaintenanceSchedulesCommand implements CommandMessage
{
}
