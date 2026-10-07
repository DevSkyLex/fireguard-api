<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Port\Outbound;

use ServiceRequest\Application\Contract\Target\ServiceRequestSiteTarget;

/**
 * Interface ServiceRequestSiteTargetPort
 *
 * Resolves a published, same-organization root site from an equipment facility or an explicit site.
 * A supplied facility must belong to the explicit site when both are provided.
 *
 * @category Port
 */
interface ServiceRequestSiteTargetPort
{
  /**
   * Serializes target hierarchy changes before equipment and facility row locks.
   */
  public function lock(string $organizationId): void;

  public function find(string $organizationId, ?string $siteId, ?string $facilityId): ?ServiceRequestSiteTarget;
}
