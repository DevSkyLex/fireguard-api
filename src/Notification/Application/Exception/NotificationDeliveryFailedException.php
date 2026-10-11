<?php

declare(strict_types=1);

namespace Notification\Application\Exception;

use RuntimeException;

/**
 * Exception NotificationDeliveryFailedException.
 *
 * Leaves a durable request unconsumed when a channel requires another attempt.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationDeliveryFailedException extends RuntimeException
{
}
