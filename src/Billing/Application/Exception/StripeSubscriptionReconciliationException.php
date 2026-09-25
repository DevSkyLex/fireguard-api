<?php

declare(strict_types=1);

namespace Billing\Application\Exception;

use RuntimeException;

/** Current Stripe state cannot be safely reconciled with the local subscription. */
final class StripeSubscriptionReconciliationException extends RuntimeException
{
}
