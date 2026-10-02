<?php

declare(strict_types=1);

namespace Maintenance\Application\Exception;

use RuntimeException;

/**
 * Exception MaintenanceReminderDeliveryException
 *
 * Signals a retryable maintenance reminder failure to the durable delivery consumer.
 *
 * @category Exception
 */
final class MaintenanceReminderDeliveryException extends RuntimeException
{
}
