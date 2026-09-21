<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/** Port DurableEventContextPort. Identifies an at-least-once event delivery. */
interface DurableEventContextPort
{
  public function eventId(): ?string;

  public function actorUserId(): ?string;
}
