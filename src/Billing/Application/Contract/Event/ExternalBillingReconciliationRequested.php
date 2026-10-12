<?php

declare(strict_types=1);

namespace Billing\Application\Contract\Event;

/**
 * Event ExternalBillingReconciliationRequested.
 *
 * Contains only the verified organization and opaque execution identity, never Stripe secrets or hosted URLs.
 *
 * @category Event
 */
final readonly class ExternalBillingReconciliationRequested
{
  /**
   * @param list<string> $purposes the external effects whose outcomes require reconciliation
   */
  public function __construct(public string $organizationId, public string $executionId, public string $operation, public array $purposes)
  {
  }
}
