<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Spatial;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Outbound\FacilitySpatialReadPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_keys;
use function array_unique;
use function array_values;

/**
 * Adapter FacilitySpatialReadAdapter.
 *
 * Loads ancestry and authorized plan identities with two bounded reads.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilitySpatialReadAdapter implements FacilitySpatialReadPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param EntityManagerInterface $entityManager main persistence connection
   */
  public function __construct(#[Autowire(service: 'doctrine.orm.main_entity_manager')] private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method readContext.
   *
   * @param string $organizationId organization whose rows may enter the snapshot
   * @param list<string> $facilityIds
   * @param list<string> $attachmentIds
   */
  public function readContext(string $organizationId, array $facilityIds, array $attachmentIds = []): FacilitySpatialContext
  {
    if ([] === $facilityIds) {
      return new FacilitySpatialContext();
    }
    $connection = $this->entityManager->getConnection();
    /** @var list<array{id: string, parent_facility_id: ?string, type: string, record_status: string}> $rows */
    $rows = $connection->executeQuery(<<<'SQL'
      WITH RECURSIVE ancestors AS (
        SELECT id, parent_facility_id, type, record_status, ARRAY[id]::text[] AS visited
        FROM facilities WHERE organization_id = :organization AND id IN (:facilities)
        UNION ALL
        SELECT parent.id, parent.parent_facility_id, parent.type, parent.record_status, ancestors.visited || parent.id::text
        FROM facilities parent JOIN ancestors ON parent.id = ancestors.parent_facility_id
        WHERE parent.organization_id = :organization AND NOT parent.id = ANY(ancestors.visited)
      )
      SELECT DISTINCT id, parent_facility_id, type, record_status FROM ancestors
      SQL, ['organization' => $organizationId, 'facilities' => array_values(array_unique($facilityIds))], ['facilities' => ArrayParameterType::STRING])->fetchAllAssociative();
    $facilities = [];
    foreach ($rows as $row) {
      $facilities[(string) $row['id']] = ['parentId' => null !== $row['parent_facility_id'] ? (string) $row['parent_facility_id'] : null, 'type' => (string) $row['type'], 'recordStatus' => (string) $row['record_status']];
    }
    if ([] === $facilities) {
      return new FacilitySpatialContext();
    }
    $plans = [];
    /** @var list<array{id: string, facility_id: string, is_primary_plan: bool|int|string, calibration_building_id: ?string}> $planRows */
    $planRows = $connection->executeQuery(<<<'SQL'
      SELECT plan.id, plan.facility_id, plan.is_primary_plan, frame.id AS calibration_building_id
      FROM facility_attachments plan JOIN facilities owner ON owner.id = plan.facility_id
      LEFT JOIN facilities frame ON frame.id = plan.calibration_building_id AND frame.organization_id = :organization
      WHERE owner.organization_id = :organization AND plan.kind = 'floor_plan'
        AND (plan.id IN (:attachments) OR (plan.facility_id IN (:facilities) AND plan.is_primary_plan))
      SQL, ['organization' => $organizationId, 'attachments' => array_values(array_unique($attachmentIds)), 'facilities' => array_keys($facilities)], ['attachments' => ArrayParameterType::STRING, 'facilities' => ArrayParameterType::STRING])->fetchAllAssociative();
    foreach ($planRows as $row) {
      $plans[(string) $row['id']] = ['facilityId' => (string) $row['facility_id'], 'primary' => true === $row['is_primary_plan'] || 1 === $row['is_primary_plan'] || 't' === $row['is_primary_plan'], 'calibrationBuildingId' => null !== $row['calibration_building_id'] ? (string) $row['calibration_building_id'] : null];
    }

    return new FacilitySpatialContext($facilities, $plans);
  }
  // #endregion
}
