<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Shared;

use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Outbound\{CurrentActorPort, DurableEventContextPort};
use Symfony\Bundle\SecurityBundle\Security;

/** Adapter CurrentActorAdapter. A durable delivery takes precedence over ambient request state. */
final readonly class CurrentActorAdapter implements CurrentActorPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the CurrentActorAdapter dependencies and state.
   *
   * @access public
   *
   * @param Security $security the security
   * @param DurableEventContextPort $events the events
   *
   * @return void
   */
  public function __construct(private Security $security, private DurableEventContextPort $events)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method userId
   *
   * Returns the current actor user identifier, or null when the caller has no user identity.
   *
   * @access public
   *
   * @return ?string the current actor user identifier, or null for a non-user actor
   */
  public function userId(): ?string
  {
    if (null !== $this->events->eventId()) {
      return $this->events->actorUserId();
    }
    $user = $this->security->getUser();

    return $user instanceof SecurityUser ? $user->getId() : null;
  }
  // #endregion
}
