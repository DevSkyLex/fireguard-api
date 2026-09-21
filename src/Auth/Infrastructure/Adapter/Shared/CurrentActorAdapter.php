<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Shared;

use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Outbound\{CurrentActorPort, DurableEventContextPort};
use Symfony\Bundle\SecurityBundle\Security;

/** Adapter CurrentActorAdapter. A durable delivery takes precedence over ambient request state. */
final readonly class CurrentActorAdapter implements CurrentActorPort
{
  public function __construct(private Security $security, private DurableEventContextPort $events)
  {
  }

  public function userId(): ?string
  {
    if (null !== $this->events->eventId()) {
      return $this->events->actorUserId();
    }
    $user = $this->security->getUser();

    return $user instanceof SecurityUser ? $user->getId() : null;
  }
}
