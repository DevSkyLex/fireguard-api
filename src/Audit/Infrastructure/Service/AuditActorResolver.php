<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Service;

use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Outbound\DurableEventContextPort;
use Symfony\Bundle\SecurityBundle\Security;

/** Resolves the explicit, durable-event or authenticated audit actor. */
final readonly class AuditActorResolver
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the security context and durable event context used to resolve audit attribution.
   *
   * @access public
   *
   * @param Security $security security context used to inspect the authenticated actor
   * @param DurableEventContextPort $eventContext durable event metadata used when resolving an asynchronous actor
   *
   * @return void
   */
  public function __construct(
    private Security $security,
    private DurableEventContextPort $eventContext,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * @return array{type: string, id: ?string, email: ?string}
   */
  public function resolve(?string $explicitUserId = null, ?string $explicitEmail = null): array
  {
    $user = $this->security->getUser();
    $tokenId = $user instanceof SecurityUser ? $user->getId() : null;
    $tokenEmail = $user instanceof SecurityUser ? $user->getUserIdentifier() : null;
    $actorId = $explicitUserId ?? (null !== $this->eventContext->eventId() ? $this->eventContext->actorUserId() : $tokenId);

    if (null === $actorId) {
      return ['type' => 'system', 'id' => null, 'email' => null];
    }

    return [
      'type' => 'user',
      'id' => $actorId,
      'email' => $explicitEmail ?? ($actorId === $tokenId ? $tokenEmail : null),
    ];
  }
  // #endregion
}
