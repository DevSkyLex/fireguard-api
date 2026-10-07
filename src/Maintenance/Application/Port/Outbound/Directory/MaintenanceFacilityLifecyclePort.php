<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Directory;

/** Facility owner decides whether the equipment's site ancestry is archived. */
interface MaintenanceFacilityLifecyclePort
{
  public function isArchived(?string $facilityId, string $organizationId): bool;
}
