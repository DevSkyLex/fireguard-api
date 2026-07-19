<?php

declare(strict_types=1);

namespace Webhook\Domain\Exception;

use RuntimeException;

/**
 * Exception WebhookDeliveryAttemptFailedException.
 *
 * Thrown by `DeliverWebhookHandler` after recording a non-terminal failed
 * attempt, purely to signal Messenger's `webhook` transport retry/backoff
 * (`retry_strategy`) to redeliver the message. Never thrown for a terminal
 * failure — that path records `status: failed` and returns normally.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookDeliveryAttemptFailedException extends RuntimeException
{
}
