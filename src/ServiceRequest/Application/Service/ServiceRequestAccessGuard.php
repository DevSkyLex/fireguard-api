<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Service;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/** Class ServiceRequestAccessGuard. Separates hidden organization scope from missing capabilities. @category Service */
final readonly class ServiceRequestAccessGuard
{
  public function __construct(private OrganizationAuthorizationPort $authorization)
  {
  }

  public function assertAccess(string $actorId, string $organizationId, string $action): void
  {
    try {
      new Uuid($organizationId);
    } catch (InvalidValueException) {
      throw ServiceRequestException::notFound();
    }
    $permission = 'organization.service_requests.' . $action;
    $decision = $this->authorization->resolveAccess($actorId, $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw ServiceRequestException::notFound();
    }
    if (!$decision->isGranted()) {
      throw ServiceRequestException::denied();
    }
  }

  public function assertRevision(int $actualRevision, ?int $expectedRevision): void
  {
    if (null === $expectedRevision) {
      throw new ServiceRequestException('service_request_precondition_required', 'If-Match is required for this mutation.');
    }
    if ($actualRevision !== $expectedRevision) {
      throw ServiceRequestException::stale();
    }
  }
}
