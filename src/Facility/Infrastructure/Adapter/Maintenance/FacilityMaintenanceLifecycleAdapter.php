<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceFacilityLifecyclePort;

/** Resolves lifecycle through the facility ancestry on the main database. */
final readonly class FacilityMaintenanceLifecycleAdapter implements MaintenanceFacilityLifecyclePort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function isArchived(?string $facilityId, string $organizationId): bool
  {
    if (null === $facilityId) {
      return false;
    }

    return (bool) $this->entityManager->getConnection()->fetchOne(
      "WITH RECURSIVE ancestry AS (
        SELECT id, parent_facility_id, status FROM facilities WHERE id = :id AND organization_id = :organization AND record_status = 'published'
        UNION
        SELECT f.id, f.parent_facility_id, f.status FROM facilities f JOIN ancestry a ON f.id = a.parent_facility_id WHERE f.organization_id = :organization
      ) SELECT CASE WHEN NOT EXISTS (SELECT 1 FROM ancestry) OR EXISTS (SELECT 1 FROM ancestry WHERE status = 'archived') THEN 1 ELSE 0 END",
      ['id' => $facilityId, 'organization' => $organizationId],
    );
  }
}
