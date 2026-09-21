<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Shared\Application\Port\Outbound\DurableEventContextPort;
use Symfony\Contracts\Service\ResetInterface;

/** Context DurableEventContext. Scoped to one delivery, including nested dispatches. */
final class DurableEventContext implements DurableEventContextPort, ResetInterface
{
  private ?string $id = null;

  private ?string $actor = null;

  public function actorUserId(): ?string
  {
    return $this->actor;
  }

  public function eventId(): ?string
  {
    return $this->id;
  }

  /**
   * @param callable():void $delivery
   */
  public function deliver(string $id, callable $delivery, ?string $actorUserId = null): void
  {
    $previous = $this->id;
    $previousActor = $this->actor;
    $this->id = $id;
    $this->actor = $actorUserId;

    try {
      $delivery();
    } finally {
      $this->id = $previous;
      $this->actor = $previousActor;
    }
  }

  public function reset(): void
  {
    $this->id = null;
    $this->actor = null;
  }
}
