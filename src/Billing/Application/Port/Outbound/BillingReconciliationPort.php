<?php

declare(strict_types=1);

namespace Billing\Application\Port\Outbound;

use Billing\Application\Contract\Stripe\StripeEvent;

/**
 * Serializes billing changes and journals successful webhook reconciliation in main.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface BillingReconciliationPort
{
  /**
   * Reread local and remote state inside the callback, after acquiring the lock.
   *
   * @template T
   *
   * @param callable(): T $operation
   *
   * @return T
   */
  public function synchronized(string $organizationId, callable $operation): mixed;

  /**
   * Runs once per event ID and environment, under the organization lock.
   * The callback, subscription, organization plan and journal commit together.
   * Any exception rolls everything back and permits a later delivery to retry.
   *
   * @param callable(): void $operation
   */
  public function processEvent(string $organizationId, StripeEvent $event, callable $operation): void;
}
