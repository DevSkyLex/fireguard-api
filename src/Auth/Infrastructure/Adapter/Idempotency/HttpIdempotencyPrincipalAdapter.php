<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Contract\User\{AuthenticatedPrincipal, AuthenticatedUser};
use Shared\Application\Contract\Http\HttpIdempotencyPrincipal;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyPrincipalPort;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Publishes the authentication owner's verified actor to the HTTP receipt boundary.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyPrincipalAdapter implements HttpIdempotencyPrincipalPort
{
  /**
   * @param Security $security current request security context
   */
  public function __construct(private Security $security)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function current(): ?HttpIdempotencyPrincipal
  {
    $user = $this->security->getUser();

    return $user instanceof AuthenticatedUser ? new HttpIdempotencyPrincipal($user->getId(), $user instanceof AuthenticatedPrincipal ? $user->getTenantId() : null) : null;
  }
}
