<?php

declare(strict_types=1);

namespace Customer\Domain\Event;

use DateTimeImmutable;

/**
 * Class CustomerChangedEvent
 *
 * Records a persisted customer identity change without copying contact information.
 *
 * @category Event
 */
final readonly class CustomerChangedEvent
{
  public function __construct(public string $organizationId, public string $customerId, public string $change, public int $revision, public DateTimeImmutable $occurredAt)
  {
  }
}
