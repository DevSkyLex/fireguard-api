<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Service;

use ServiceRequest\Application\Port\Outbound\{ServiceRequestEquipmentTargetPort, ServiceRequestSiteTargetPort};
use ServiceRequest\Domain\Exception\ServiceRequestException;

/** Class ServiceRequestTargetGuard. Resolves current published targets and prevents new work on retired sites or equipment. @category Service */
final readonly class ServiceRequestTargetGuard
{
  public function __construct(private ServiceRequestEquipmentTargetPort $equipment, private ServiceRequestSiteTargetPort $sites)
  {
  }

  /**
   * @return array{equipment:?array{id:string,name:?string,assetCode:?string,status:string},site:?array{id:string,name:string},customer:?array{id:string,name:string}}
   */
  public function snapshot(string $organizationId, ?string $equipmentId, ?string $siteId): array
  {
    if (null === $equipmentId && null === $siteId) {
      throw ServiceRequestException::invalid('A repair request must target an equipment or a site.');
    }
    $this->sites->lock($organizationId);
    $equipment = null === $equipmentId ? null : $this->equipment->find($equipmentId, $organizationId);
    if (null !== $equipmentId && null === $equipment) {
      throw ServiceRequestException::invalid('The repair target is unavailable in this organization.');
    }
    if (null !== $equipment && 'decommissioned' === $equipment->status) {
      throw ServiceRequestException::transitionConflict('Retired equipment cannot receive new repair work.');
    }
    if (null !== $siteId && null !== $equipment && null === $equipment->facilityId) {
      throw ServiceRequestException::invalid('The equipment does not belong to the selected site.');
    }
    $site = null === $siteId && null === $equipment?->facilityId ? null : $this->sites->find($organizationId, $siteId, $equipment?->facilityId);
    if ((null !== $siteId || null !== $equipment?->facilityId) && null === $site) {
      throw ServiceRequestException::invalid('The repair target is unavailable in this organization.');
    }
    if (null !== $site && $site->archived) {
      throw ServiceRequestException::transitionConflict('Archived sites cannot receive new repair work.');
    }

    return ['equipment' => null === $equipment ? null : ['id' => $equipment->id, 'name' => $equipment->name, 'assetCode' => $equipment->assetCode, 'status' => $equipment->status], 'site' => null === $site ? null : ['id' => $site->id, 'name' => $site->name], 'customer' => $site?->customer];
  }
}
