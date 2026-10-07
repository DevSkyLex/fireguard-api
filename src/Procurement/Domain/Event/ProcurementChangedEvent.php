<?php

declare(strict_types=1);

namespace Procurement\Domain\Event;

use DateTimeImmutable;

/** Persisted procurement changes carry identities only, without confidential prices or contacts. */
final readonly class ProcurementChangedEvent
{
  public function __construct(public string $organizationId, public string $resourceId, public string $change, public DateTimeImmutable $occurredAt)
  {
  }
}
