<?php

declare(strict_types=1);

namespace Webhook\Domain\Exception;

use RuntimeException;

/**
 * Exception WebhookValidationException.
 *
 * Raised for business-rule violations: an unknown event type outside the
 * curated allowlist, an invalid/insecure target URL, or exceeding the
 * per-organization active subscription cap.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookValidationException extends RuntimeException
{
}
