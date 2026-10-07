<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\ServiceRequest;

use Doctrine\DBAL\Connection;
use ServiceRequest\Application\Contract\Source\ServiceRequestOriginUnavailable;
use ServiceRequest\Application\Port\Outbound\{ServiceRequestOriginPort, ServiceRequestSiteTargetPort};

use function is_string;

/**
 * Adapter ServiceRequestOriginAdapter.
 *
 * Checks published inspection evidence within the request organization and target.
 * Unknown, foreign, unpublished and mismatched references have the same outcome.
 *
 * @category Adapter
 */
final readonly class ServiceRequestOriginAdapter implements ServiceRequestOriginPort
{
  /**
   * Method __construct.
   *
   * @param Connection $connection explicit main connection
   * @param ServiceRequestSiteTargetPort $sites owning Facility root-site resolver
   */
  public function __construct(private Connection $connection, private ServiceRequestSiteTargetPort $sites)
  {
  }

  /**
   * Method assertMatches.
   *
   * @param string $organizationId authorized request organization
   * @param ?string $equipmentId qualified equipment, if known
   * @param ?string $siteId root site resolved by the request target guard
   * @param ?string $originInspectionId optional published inspection evidence
   * @param ?string $originNonConformityId optional finding owned by that inspection
   *
   * @throws ServiceRequestOriginUnavailable when the evidence cannot describe this target
   */
  public function assertMatches(string $organizationId, ?string $equipmentId, ?string $siteId, ?string $originInspectionId, ?string $originNonConformityId): void
  {
    if (null === $originInspectionId && null === $originNonConformityId) {
      return;
    }

    $row = null === $originNonConformityId
      ? $this->connection->fetchAssociative("SELECT id, equipment_id, facility_id FROM inspections WHERE id = :inspectionId AND organization_id = :organizationId AND record_status = 'published'", ['inspectionId' => $originInspectionId, 'organizationId' => $organizationId])
      : $this->connection->fetchAssociative("SELECT i.id, i.equipment_id, i.facility_id FROM non_conformities n INNER JOIN inspections i ON i.id = n.inspection_id WHERE n.id = :findingId AND i.organization_id = :organizationId AND i.record_status = 'published'", ['findingId' => $originNonConformityId, 'organizationId' => $organizationId]);

    if (false === $row || !isset($row['id'], $row['equipment_id']) || !is_string($row['id']) || !is_string($row['equipment_id'])) {
      throw new ServiceRequestOriginUnavailable();
    }
    if ((null !== $originInspectionId && $row['id'] !== $originInspectionId) || (null !== $equipmentId && $row['equipment_id'] !== $equipmentId)) {
      throw new ServiceRequestOriginUnavailable();
    }
    if (null !== $siteId) {
      $facilityId = $row['facility_id'] ?? null;
      if (!is_string($facilityId) || null === $this->sites->find($organizationId, $siteId, $facilityId)) {
        throw new ServiceRequestOriginUnavailable();
      }
    }
  }
}
