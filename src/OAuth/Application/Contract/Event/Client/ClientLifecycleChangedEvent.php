<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Event\Client;

use DateTimeImmutable;

/**
 * Publishes immutable client lifecycle facts without domain values or client secrets.
 *
 * @category Event
 */
final readonly class ClientLifecycleChangedEvent
{
  /**
   * @param array<string, mixed> $payload the original scalar owner event facts
   */
  public function __construct(public string $eventType, public string $eventId, public string $clientId, public DateTimeImmutable $occurredAt, public array $payload)
  {
  }
}
