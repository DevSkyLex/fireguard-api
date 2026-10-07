<?php

declare(strict_types=1);

namespace Customer\Application\Service;

use Customer\Domain\Exception\CustomerException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Domain\ValueObject\Uuid;

/** Class CustomerAccessGuard. Distinguishes entitlement denial from hidden organization scope. @category Service */
final readonly class CustomerAccessGuard
{
  public function __construct(private OrganizationAuthorizationPort $authorization)
  {
  }

  public function assertAccess(string $actorId, string $organizationId, bool $write): void
  {
    new Uuid($organizationId);
    $decision = $this->authorization->resolveAccess($actorId, $organizationId, $write ? 'organization.customers.manage' : 'organization.customers.read');
    if ($decision->isOutsideScope()) {
      throw CustomerException::notFound();
    }
    if (!$decision->isGranted()) {
      throw CustomerException::denied();
    }
  }
}
