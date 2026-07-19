<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ExpireStaleApprovalRequestsCommand.
 *
 * Triggered hourly by {@see \Approval\Infrastructure\Scheduler\ApprovalScheduleProvider}.
 * Carries no payload: the sweep always processes every organization.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExpireStaleApprovalRequestsCommand implements CommandMessage
{
}
