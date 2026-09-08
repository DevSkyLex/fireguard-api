<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Sweep\SendWeeklyDigests;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SendWeeklyDigestsCommand.
 *
 * Carrier-less sweep trigger: the weekly schedule dispatches it with no
 * payload, mirroring `EscalateNonConformitySlaBreachesCommand`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendWeeklyDigestsCommand implements CommandMessage
{
}
