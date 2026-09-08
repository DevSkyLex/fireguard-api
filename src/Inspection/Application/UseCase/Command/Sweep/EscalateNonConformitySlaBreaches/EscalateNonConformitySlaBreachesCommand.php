<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Sweep\EscalateNonConformitySlaBreaches;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase EscalateNonConformitySlaBreachesCommand.
 *
 * Carrier-less sweep trigger: the hourly schedule dispatches it with no
 * payload, mirroring `RecomputeMaintenanceSchedulesCommand`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EscalateNonConformitySlaBreachesCommand implements CommandMessage
{
}
