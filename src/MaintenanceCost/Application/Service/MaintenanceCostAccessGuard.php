<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Domain\ValueObject\Uuid;

/** Class MaintenanceCostAccessGuard. Protects private finance independently of operational permissions. @category Service */
final readonly class MaintenanceCostAccessGuard
{
  public function __construct(private OrganizationAuthorizationPort $authorization)
  {
  }

  public function assertAccess(string $actorId, string $organizationId, bool $write): void
  {
    new Uuid($organizationId);
    $decision = $this->authorization->resolveAccess($actorId, $organizationId, $write ? 'organization.maintenance_cost.manage' : 'organization.maintenance_cost.read');
    if ($decision->isOutsideScope()) {
      throw MaintenanceCostException::notFound();
    }
    if (!$decision->isGranted()) {
      throw MaintenanceCostException::denied();
    }
  }

  public function canManage(string $actorId, string $organizationId): bool
  {
    return $this->authorization->resolveAccess($actorId, $organizationId, 'organization.maintenance_cost.manage')->isGranted();
  }
}
