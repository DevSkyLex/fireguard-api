<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\Event;

use DateTimeImmutable;

/** Class ServiceRequestChangedEvent. Records a committed workflow fact without copying request contents. @category Event */
final readonly class ServiceRequestChangedEvent
{
  public function __construct(public string $organizationId, public string $requestId, public string $change, public int $revision, public DateTimeImmutable $occurredAt)
  {
  }
}
