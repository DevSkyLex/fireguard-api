<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

/** Message OutboxEvent. The identity and event snapshot survive every transport retry. */
final readonly class OutboxEvent
{
  public function __construct(public string $id, public object $event, public ?string $actorUserId = null)
  {
  }
}
