<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Port\Outbound;

/**
 * Interface ServiceRequestOriginPort
 *
 * Validates optional inspection/finding evidence against the scoped repair target.
 *
 * @category Port
 */
interface ServiceRequestOriginPort
{
  /**
   * @throws \ServiceRequest\Application\Contract\Source\ServiceRequestOriginUnavailable
   */
  public function assertMatches(string $organizationId, ?string $equipmentId, ?string $siteId, ?string $originInspectionId, ?string $originNonConformityId): void;
}
