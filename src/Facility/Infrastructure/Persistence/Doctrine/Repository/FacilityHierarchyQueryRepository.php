<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus};

use function array_map;
use function implode;
use function is_numeric;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Repository FacilityHierarchyQueryRepository.
 *
 * Recursive hierarchy reads and raw spatial projections for facility persistence.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityHierarchyQueryRepository
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Shares the explicitly selected main manager with aggregate persistence.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the same explicit main manager as the repository
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method findAncestors.
   *
   * Walks the facility's parent chain upward via a single recursive CTE,
   * mirroring {@see descendantIds} in the opposite direction. Only PUBLISHED
   * records are walked (draft intervention scratchpads are invisible here),
   * and the result is ordered root-first — direct parent last — excluding the
   * facility itself. A root facility with no parent yields an empty list.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier whose ancestors are resolved
   *
   * @return list<array{id: string, name: string, type: string}> the ancestor breadcrumb, root first
   */
  public function findAncestors(string $facilityId): array
  {
    $sql = <<<'SQL'
      WITH RECURSIVE ancestors AS (
          SELECT f.id, f.name, f.type, f.parent_facility_id, 1 AS depth
          FROM facilities f
          INNER JOIN facilities origin ON origin.id = :facilityId
          WHERE f.id = origin.parent_facility_id
            AND f.record_status = :published
          UNION
          SELECT parent.id, parent.name, parent.type, parent.parent_facility_id, ancestors.depth + 1
          FROM facilities parent
          INNER JOIN ancestors ON parent.id = ancestors.parent_facility_id
          WHERE parent.record_status = :published
      )
      SELECT id, name, type FROM ancestors ORDER BY depth DESC
      SQL;

    /** @var list<array{id: string, name: string, type: string}> */
    return $this->entityManager->getConnection()->executeQuery($sql, [
      'facilityId' => $facilityId,
      'published' => 'published',
    ])->fetchAllAssociative();
  }

  /**
   * Method findAncestorsByFacilityIds.
   *
   * Batch ancestor walks are bounded by visited identifiers and never cross organizations.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization scope
   * @param list<string> $facilityIds the page to enrich
   *
   * @return array<string, list<array{id: string, name: string, type: string}>> ancestor breadcrumbs keyed by facility
   */
  public function findAncestorsByFacilityIds(FacilityOrganizationId $organizationId, array $facilityIds): array
  {
    if ([] === $facilityIds) {
      return [];
    }

    $sql = <<<'SQL'
      WITH RECURSIVE ancestors AS (
        SELECT origin.id AS origin_id, origin.intervention_id, origin.record_status AS origin_record_status, parent.id,
          parent.name, parent.type, parent.parent_facility_id, 1 AS depth,
          ARRAY[origin.id, parent.id]::text[] AS visited
        FROM facilities origin
        INNER JOIN facilities parent ON parent.id = origin.parent_facility_id
          AND parent.organization_id = origin.organization_id
        WHERE origin.organization_id = :organizationId AND origin.id IN (:facilityIds)
          AND parent.id <> origin.id
          AND (parent.record_status = 'published' OR
            (origin.record_status = 'draft' AND parent.record_status = 'draft'
              AND parent.intervention_id = origin.intervention_id))
        UNION ALL
        SELECT ancestors.origin_id, ancestors.intervention_id, ancestors.origin_record_status, parent.id,
          parent.name, parent.type, parent.parent_facility_id, ancestors.depth + 1,
          ancestors.visited || parent.id
        FROM ancestors
        INNER JOIN facilities parent ON parent.id = ancestors.parent_facility_id
        WHERE parent.organization_id = :organizationId
          AND NOT parent.id = ANY(ancestors.visited)
          AND (parent.record_status = 'published' OR
            (ancestors.origin_record_status = 'draft' AND parent.record_status = 'draft'
              AND parent.intervention_id = ancestors.intervention_id))
      )
      SELECT origin_id, id, name, type FROM ancestors ORDER BY origin_id, depth DESC
      SQL;

    /** @var list<array{origin_id: string, id: string, name: string, type: string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'organizationId' => (string) $organizationId,
      'facilityIds' => $facilityIds,
    ], ['facilityIds' => ArrayParameterType::STRING])->fetchAllAssociative();

    $paths = [];
    foreach ($rows as $row) {
      $paths[$row['origin_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'type' => $row['type']];
    }

    return $paths;
  }

  /**
   * Method hasActiveDescendants.
   *
   * Reports whether the facility has at least one active (non-archived,
   * published) descendant anywhere in its sub-tree, without hydrating the tree:
   * a single recursive CTE with an existence probe. The walk covers published
   * records only, but does traverse archived intermediate nodes so a live
   * descendant under an archived branch is still detected.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityId $facilityId the root facility identifier
   *
   * @return bool whether an active descendant exists
   */
  public function hasActiveDescendants(FacilityOrganizationId $organizationId, FacilityId $facilityId): bool
  {
    $sql = <<<'SQL'
      WITH RECURSIVE descendants AS (
          SELECT id, status
          FROM facilities
          WHERE parent_facility_id = :rootId
            AND organization_id = :organizationId
            AND record_status = :published
          UNION
          SELECT child.id, child.status
          FROM facilities child
          INNER JOIN descendants ON child.parent_facility_id = descendants.id
          WHERE child.organization_id = :organizationId
            AND child.record_status = :published
      )
      SELECT id FROM descendants WHERE status <> :archived LIMIT 1
      SQL;

    $match = $this->entityManager->getConnection()->executeQuery($sql, [
      'rootId' => (string) $facilityId,
      'organizationId' => (string) $organizationId,
      'published' => 'published',
      'archived' => FacilityStatus::ARCHIVED->value,
    ])->fetchOne();

    return false !== $match;
  }

  /**
   * Method findZonesForPlanAttachment.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the scoped organization
   * @param FacilityId $rootFacilityId the projected subtree root
   * @param string $attachmentId the current display plan
   *
   * @return list<array{facilityId: string, name: string, type: string, status: string, points: list<array{0: float, 1: float}>, attachmentId: string}> the retained spatial candidates
   */
  public function findZonesForPlanAttachment(
    FacilityOrganizationId $organizationId,
    FacilityId $rootFacilityId,
    string $attachmentId,
  ): array {
    $sql = <<<'SQL'
      WITH RECURSIVE subtree AS (
          SELECT id, status, type, name, plan_geometry
          FROM facilities
          WHERE id = :rootId
            AND organization_id = :organizationId
            AND record_status = :published
          UNION
          SELECT child.id, child.status, child.type, child.name, child.plan_geometry
          FROM facilities child
          INNER JOIN subtree ON child.parent_facility_id = subtree.id
          WHERE child.organization_id = :organizationId
            AND child.record_status = :published
      )
      SELECT id, status, type, name, plan_geometry
      FROM subtree
      WHERE plan_geometry IS NOT NULL
      SQL;

    /** @var list<array{id: string, status: string, type: string, name: string, plan_geometry: string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'rootId' => (string) $rootFacilityId,
      'organizationId' => (string) $organizationId,
      'published' => 'published',
    ])->fetchAllAssociative();

    $zones = [];
    foreach ($rows as $row) {
      /** @var array{attachmentId: string, points: list<array{0: float, 1: float}>} $geometry */
      $geometry = json_decode($row['plan_geometry'], true, 512, JSON_THROW_ON_ERROR);

      $zones[] = [
        'facilityId' => $row['id'],
        'name' => $row['name'],
        'type' => $row['type'],
        'status' => $row['status'],
        'points' => $geometry['points'],
        'attachmentId' => $geometry['attachmentId'],
      ];
    }

    return $zones;
  }

  /**
   * Method findBuildingFloors.
   *
   * Lists the direct children of a building that are floors — published,
   * ordered by their stacking level, then creation, then identifier — each
   * carrying its own plan geometry (if any) and the attachment identity of
   * its primary floor plan (if any). Raw rows only: no contour cascade, no
   * leaf filtering — the 3D building view use case applies those rules.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityId $buildingId the building facility identifier
   *
   * @return list<array{
   *   facilityId: string,
   *   name: string,
   *   status: string,
   *   levelIndex: ?int,
   *   elevationMeters: ?float,
   *   heightMeters: ?float,
   *   planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>},
   *   primaryPlanAttachmentId: ?string,
   *   primaryPlanImageWidth: ?int,
   *   primaryPlanImageHeight: ?int,
   *   primaryPlanCalibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, primaryPlanCalibrationBuildingId: ?string,
   * }> the building's floors, in render order
   */
  public function findBuildingFloors(
    FacilityOrganizationId $organizationId,
    FacilityId $buildingId,
  ): array {
    $sql = <<<'SQL'
      WITH RECURSIVE building_subtree AS (
        SELECT id, ARRAY[id]::text[] AS visited FROM facilities
        WHERE id = :buildingId AND organization_id = :organizationId AND record_status = :published
        UNION ALL
        SELECT child.id, building_subtree.visited || child.id::text
        FROM facilities child JOIN building_subtree ON child.parent_facility_id = building_subtree.id
        WHERE child.organization_id = :organizationId AND child.record_status = :published
          AND child.type <> 'building' AND NOT child.id = ANY(building_subtree.visited)
      )
      SELECT
          floor.id,
          floor.name,
          floor.status,
          floor.level_index,
          floor.elevation_meters,
          floor.height_meters,
          floor.plan_geometry,
          plan.id AS primary_plan_attachment_id,
          plan.image_width AS primary_plan_image_width,
          plan.image_height AS primary_plan_image_height,
          plan.calibration AS primary_plan_calibration,
          frame.id AS primary_plan_calibration_building_id
      FROM facilities floor
      LEFT JOIN facility_attachments plan
          ON plan.facility_id = floor.id
          AND plan.kind = 'floor_plan'
           AND plan.is_primary_plan
      LEFT JOIN facilities frame ON frame.id = plan.calibration_building_id AND frame.organization_id = :organizationId
      WHERE floor.id IN (SELECT id FROM building_subtree)
        AND floor.organization_id = :organizationId
        AND floor.type = 'floor'
        AND floor.record_status = :published
      ORDER BY floor.level_index ASC NULLS LAST, floor.created_at ASC, floor.id ASC
      SQL;

    /**
     * @var list<array{
     *   id: string,
     *   name: string,
     *   status: string,
     *   level_index: ?string,
     *   elevation_meters: ?string,
     *   height_meters: ?string,
     *   plan_geometry: ?string,
     *   primary_plan_attachment_id: ?string,
     *   primary_plan_image_width: ?string,
     *   primary_plan_image_height: ?string,
     *   primary_plan_calibration: ?string,
     *   primary_plan_calibration_building_id: ?string,
     * }> $rows
     */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'buildingId' => (string) $buildingId,
      'organizationId' => (string) $organizationId,
      'published' => 'published',
    ])->fetchAllAssociative();

    $floors = [];
    foreach ($rows as $row) {
      $planGeometry = null;
      if (null !== $row['plan_geometry']) {
        /** @var array{attachmentId: string, points: list<array{0: float, 1: float}>} $planGeometry */
        $planGeometry = json_decode($row['plan_geometry'], true, 512, JSON_THROW_ON_ERROR);
      }

      $floors[] = [
        'facilityId' => $row['id'],
        'name' => $row['name'],
        'status' => $row['status'],
        'levelIndex' => null !== $row['level_index'] ? (int) $row['level_index'] : null,
        'elevationMeters' => null !== $row['elevation_meters'] ? (float) $row['elevation_meters'] : null,
        'heightMeters' => null !== $row['height_meters'] ? (float) $row['height_meters'] : null,
        'planGeometry' => $planGeometry,
        'primaryPlanAttachmentId' => $row['primary_plan_attachment_id'],
        'primaryPlanImageWidth' => null !== $row['primary_plan_image_width'] ? (int) $row['primary_plan_image_width'] : null,
        'primaryPlanImageHeight' => null !== $row['primary_plan_image_height'] ? (int) $row['primary_plan_image_height'] : null,
        'primaryPlanCalibration' => $this->decodeCalibration($row['primary_plan_calibration']),
        'primaryPlanCalibrationBuildingId' => $row['primary_plan_calibration_building_id'],
      ];
    }

    return $floors;
  }

  /**
   * Method findRoomsForFloors.
   *
   * For each given (floor, its primary plan attachment) pair, lists the
   * strict descendants of that floor which are zones or areas bound to that
   * same plan attachment — a single recursive CTE seeded from all the
   * bindings at once, never one query per floor. Raw rows only: leaf
   * filtering by `parentFacilityId` is the use case's job.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<array{floorId: string, attachmentId: string}> $floorPlanBindings the floors and their primary plan attachment identifiers
   *
   * @return list<array{
   *   floorId: string,
   *   facilityId: string,
   *   parentFacilityId: ?string,
   *   name: string,
   *   type: string,
   *   status: string,
   *   points: list<array{0: float, 1: float}>,
   *   attachmentId: string,
   * }> the matching rooms, unordered
   */
  public function findRoomsForFloors(
    FacilityOrganizationId $organizationId,
    array $floorPlanBindings,
  ): array {
    if ([] === $floorPlanBindings) {
      return [];
    }

    $seedRows = [];
    $params = [
      'organizationId' => (string) $organizationId,
      'published' => 'published',
    ];
    foreach ($floorPlanBindings as $index => $binding) {
      $seedRows[] = "(:floorId{$index}, :attachmentId{$index})";
      $params["floorId{$index}"] = $binding['floorId'];
      $params["attachmentId{$index}"] = $binding['attachmentId'];
    }
    $valuesList = implode(', ', $seedRows);

    $sql = <<<SQL
      WITH RECURSIVE bindings (floor_id, attachment_id) AS (
          VALUES {$valuesList}
      ),
      subtree AS (
          SELECT
              floor.id,
              floor.parent_facility_id,
              floor.type,
              floor.name,
              floor.status,
              floor.plan_geometry,
              bindings.floor_id,
              bindings.attachment_id
          FROM facilities floor
          INNER JOIN bindings ON floor.id = bindings.floor_id
          WHERE floor.organization_id = :organizationId
            AND floor.record_status = :published
          UNION ALL
          SELECT
              child.id,
              child.parent_facility_id,
              child.type,
              child.name,
              child.status,
              child.plan_geometry,
              subtree.floor_id,
              subtree.attachment_id
          FROM facilities child
          INNER JOIN subtree ON child.parent_facility_id = subtree.id
          WHERE child.organization_id = :organizationId
            AND child.record_status = :published
            AND child.type <> 'floor'
      )
      SELECT floor_id, id, parent_facility_id, name, type, status, plan_geometry
      FROM subtree
      WHERE id <> floor_id
        AND type IN ('zone', 'area')
        AND plan_geometry IS NOT NULL
      SQL;

    /**
     * @var list<array{
     *   floor_id: string,
     *   id: string,
     *   parent_facility_id: ?string,
     *   name: string,
     *   type: string,
     *   status: string,
     *   plan_geometry: string,
     * }> $rows
     */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

    $rooms = [];
    foreach ($rows as $row) {
      /** @var array{attachmentId: string, points: list<array{0: float, 1: float}>} $geometry */
      $geometry = json_decode($row['plan_geometry'], true, 512, JSON_THROW_ON_ERROR);

      $rooms[] = [
        'floorId' => $row['floor_id'],
        'facilityId' => $row['id'],
        'parentFacilityId' => $row['parent_facility_id'],
        'name' => $row['name'],
        'type' => $row['type'],
        'status' => $row['status'],
        'points' => $geometry['points'],
        'attachmentId' => $geometry['attachmentId'],
      ];
    }

    return $rooms;
  }

  /**
   * Method depthOf.
   *
   * Reports the facility's depth in its hierarchy, walking upward through
   * PUBLISHED ancestors only in a single recursive CTE. A root facility
   * (no parent) sits at depth 1.
   *
   * @since 1.0.0
   *
   * @param FacilityId $facilityId the facility identifier
   *
   * @return int the facility depth, root = 1
   */
  public function depthOf(FacilityId $facilityId): int
  {
    $sql = <<<'SQL'
      WITH RECURSIVE ancestors AS (
          SELECT id, parent_facility_id
          FROM facilities
          WHERE id = :facilityId
            AND record_status = :published
          UNION ALL
          SELECT parent.id, parent.parent_facility_id
          FROM facilities parent
          INNER JOIN ancestors ON parent.id = ancestors.parent_facility_id
          WHERE parent.record_status = :published
      )
      SELECT COUNT(*) FROM ancestors
      SQL;

    $result = $this->entityManager->getConnection()->executeQuery($sql, [
      'facilityId' => (string) $facilityId,
      'published' => 'published',
    ])->fetchOne();

    return is_numeric($result) ? (int) $result : 0;
  }

  /**
   * Method subtreeHeight.
   *
   * Reports the height of the facility's sub-tree, walking downward through
   * PUBLISHED descendants only in a single recursive CTE. A facility with no
   * descendants has height 0.
   *
   * @since 1.0.0
   *
   * @param FacilityId $facilityId the sub-tree root facility identifier
   *
   * @return int the sub-tree height, leaf = 0
   */
  public function subtreeHeight(FacilityId $facilityId): int
  {
    $sql = <<<'SQL'
      WITH RECURSIVE descendants AS (
          SELECT id, 1 AS level
          FROM facilities
          WHERE parent_facility_id = :rootId
            AND record_status = :published
          UNION ALL
          SELECT child.id, descendants.level + 1
          FROM facilities child
          INNER JOIN descendants ON child.parent_facility_id = descendants.id
          WHERE child.record_status = :published
      )
      SELECT COALESCE(MAX(level), 0) FROM descendants
      SQL;

    $result = $this->entityManager->getConnection()->executeQuery($sql, [
      'rootId' => (string) $facilityId,
      'published' => 'published',
    ])->fetchOne();

    return is_numeric($result) ? (int) $result : 0;
  }

  /**
   * Method findFacilityBindingsForFloors.
   *
   * @since 1.0.0
   *
   * @param list<string> $floorIds
   *
   * @return list<array{floorId: string, facilityId: string}>
   */
  public function findFacilityBindingsForFloors(FacilityOrganizationId $organizationId, array $floorIds): array
  {
    if ([] === $floorIds) {
      return [];
    }
    $sql = <<<'SQL'
      WITH RECURSIVE locations AS (
        SELECT f.id AS floor_id, f.id
        FROM facilities f
        WHERE f.id IN (:floorIds) AND f.organization_id = :organizationId
          AND f.type = 'floor' AND f.record_status = 'published'
        UNION ALL
        SELECT l.floor_id, child.id FROM locations l
        JOIN facilities child ON child.parent_facility_id = l.id
        WHERE child.organization_id = :organizationId
          AND child.record_status = 'published' AND child.type <> 'floor'
      )
      SELECT floor_id, id FROM locations
      SQL;
    /** @var list<array{floor_id: string, id: string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'floorIds' => $floorIds, 'organizationId' => (string) $organizationId,
    ], ['floorIds' => ArrayParameterType::STRING])->fetchAllAssociative();

    return array_map(static fn (array $row): array => ['floorId' => $row['floor_id'], 'facilityId' => $row['id']], $rows);
  }

  /**
   * Method descendantIds.
   *
   * Returns the ids of every facility beneath the given root (children,
   * grandchildren, ...) within the organization in a single recursive CTE,
   * replacing the former per-node breadth-first walk (one query per node).
   * `UNION` (not `UNION ALL`) de-duplicates, making the walk cycle-safe. Only
   * published records are walked (draft intervention scratchpads are invisible
   * here, matching the former walk); the lifecycle status is deliberately NOT
   * filtered so an archived intermediate node never hides its live descendants —
   * the caller decides which statuses to keep.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param string $rootId the root facility identifier
   *
   * @return list<string> the descendant facility ids
   */
  public function descendantIds(FacilityOrganizationId $organizationId, string $rootId): array
  {
    $sql = <<<'SQL'
      WITH RECURSIVE descendants AS (
          SELECT id
          FROM facilities
          WHERE parent_facility_id = :rootId
            AND organization_id = :organizationId
            AND record_status = :published
          UNION
          SELECT child.id
          FROM facilities child
          INNER JOIN descendants ON child.parent_facility_id = descendants.id
          WHERE child.organization_id = :organizationId
            AND child.record_status = :published
      )
      SELECT id FROM descendants
      SQL;

    /** @var list<string> */
    return $this->entityManager->getConnection()->executeQuery($sql, [
      'rootId' => $rootId,
      'organizationId' => (string) $organizationId,
      'published' => 'published',
    ])->fetchFirstColumn();
  }

  /**
   * Method decodeCalibration.
   *
   * @since 1.0.0
   *
   * @return ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}
   */
  private function decodeCalibration(?string $raw): ?array
  {
    if (null === $raw) {
      return null;
    }
    /** @var array<string, mixed> $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    return \Facility\Domain\ValueObject\PlanCalibration::fromArray($data)->toArray();
  }
  // #endregion
}
