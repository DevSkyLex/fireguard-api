<?php

declare(strict_types=1);

namespace Webhook\Application\UseCase\Command\Sweep\RecoverWebhookDeliveries;

use Shared\Application\Message\CommandMessage;

/**
 * Class RecoverWebhookDeliveriesCommand
 *
 * Requests recovery of committed reservations and expired delivery leases.
 *
 * @category UseCase
 */
final readonly class RecoverWebhookDeliveriesCommand implements CommandMessage
{
}
