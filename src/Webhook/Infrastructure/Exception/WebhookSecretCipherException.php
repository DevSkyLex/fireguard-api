<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Exception;

use RuntimeException;

/** Encryption or authentication failure for a webhook signing secret. */
final class WebhookSecretCipherException extends RuntimeException
{
}
