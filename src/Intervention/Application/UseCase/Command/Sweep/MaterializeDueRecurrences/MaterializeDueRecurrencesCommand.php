<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Sweep\MaterializeDueRecurrences;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase MaterializeDueRecurrencesCommand.
 *
 * Triggered hourly by {@see \Intervention\Infrastructure\Scheduler\InterventionScheduleProvider}.
 * Carries no payload: the sweep always processes every organization.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaterializeDueRecurrencesCommand implements CommandMessage
{
}
