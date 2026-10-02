<?php

declare(strict_types=1);

namespace Intervention\Application\Exception;

use RuntimeException;

/**
 * Class InterventionNotificationDeliveryException
 *
 * Keeps a failed durable notification retryable after its intervention outcome has committed.
 * Successful or deliberately suppressed channels remain acknowledged by the delivery boundary.
 *
 * @category Exception
 */
final class InterventionNotificationDeliveryException extends RuntimeException
{
}
