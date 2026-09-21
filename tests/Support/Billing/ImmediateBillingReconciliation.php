<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Billing\Application\Contract\Stripe\StripeEvent;
use Billing\Application\Port\Outbound\BillingReconciliationPort;

/**
 * In-memory receipt journal for handler tests; PostgreSQL tests cover locking.
 *
 * @category Test Support
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImmediateBillingReconciliation implements BillingReconciliationPort
{
  /**
   * @var array<string, true>
   */
  public array $processed = [];

  public function synchronized(string $organizationId, callable $operation): mixed
  {
    return $operation();
  }

  public function processEvent(string $organizationId, StripeEvent $event, callable $operation): void
  {
    $key = (int) $event->liveMode . ':' . $event->eventId;
    if (isset($this->processed[$key])) {
      return;
    }
    $operation();
    $this->processed[$key] = true;
  }
}
