<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\QueryBuilder;
use Facility\Application\Contract\Facility\FacilityListCriteria;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\FacilityOrganizationNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function array_map;
use function implode;
use function is_numeric;
use function json_decode;
use function mb_strtolower;
use function str_contains;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

/**
 * Repository FacilityRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityRepository extends FacilityOrganizationQueryRepository implements FacilityRepositoryPort
{
  /**
   * Method save.
   *
   * Persists the facility aggregate.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the facility aggregate
   */
  public function save(Facility $facility): void
  {
    $record = FacilityMapper::toRecord($facility);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $facility->organizationId());
    $record->organization = $organization;
    $record->parentFacility = null !== $facility->parentFacilityId()
      ? $this->entityManager->getReference(FacilityRecord::class, (string) $facility->parentFacilityId())
      : null;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof FacilityRecord) {
      $existing->organization = $organization;
      $existing->parentFacility = $record->parentFacility;
      $existing->type = $record->type;
      $existing->name = $record->name;
      $existing->code = $record->code;
      $existing->status = $record->status;
      $existing->address = $record->address;
      $existing->latitude = $record->latitude;
      $existing->longitude = $record->longitude;
      $existing->metadata = $record->metadata;
      $existing->planGeometry = $record->planGeometry;
      $existing->levelIndex = $record->levelIndex;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    try {
      $this->entityManager->flush();
    } catch (ForeignKeyConstraintViolationException $exception) {
      if ($this->isOrganizationConstraintViolation($exception)) {
        throw FacilityOrganizationNotFoundException::create();
      }

      throw $exception;
    }
  }

  /**
   * Method findById.
   *
   * Finds a facility by identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return ?Facility the facility aggregate when found
   */
  public function findById(FacilityId $id): ?Facility
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord) {
      return null;
    }

    return FacilityMapper::toDomain($record);
  }

  public function findPublishedById(FacilityId $id): ?Facility
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord || 'published' !== $record->recordStatus) {
      return null;
    }

    return FacilityMapper::toDomain($record);
  }

  /**
   * Method findChildren.
   *
   * Executes the find children operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param FacilityId $facilityId the facility id value
   * @param bool $includeArchived the include archived value
   * @param ?string $search the search value
   * @param Sorting $sorting the sorting value
   * @param int $limit the limit value
   * @param int $offset the offset value
   *
   * @return list<Facility> the find children result
   */
  public function findChildren(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    /** @var list<FacilityRecord> $records */
    $records = $this->createChildQueryBuilder($organizationId, $facilityId, $includeArchived, $search)
      ->orderBy($this->resolveSortField($sorting->field), strtoupper($sorting->direction->value))
      ->addOrderBy('f.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (FacilityRecord $record): Facility => FacilityMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countChildren.
   *
   * Executes the count children operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param FacilityId $facilityId the facility id value
   * @param bool $includeArchived the include archived value
   * @param ?string $search the search value
   *
   * @return int the count children result
   */
  public function countChildren(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
  ): int {
    return (int) $this->createChildQueryBuilder($organizationId, $facilityId, $includeArchived, $search)
      ->select('COUNT(f.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Method countChildrenByParentIds.
   *
   * Executes the count children by parent ids operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param list<FacilityId> $parentIds the parent ids value
   * @param bool $includeArchived the include archived value
   *
   * @return array<string, int> the count children by parent ids result
   */
  public function countChildrenByParentIds(
    FacilityOrganizationId $organizationId,
    array $parentIds,
    bool $includeArchived = false,
  ): array {
    if ([] === $parentIds) {
      return [];
    }

    $parentIdValues = [];
    foreach ($parentIds as $parentId) {
      $parentIdValues[] = (string) $parentId;
    }

    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('IDENTITY(f.parentFacility) AS parentId', 'COUNT(f.id) AS childCount')
      ->from(FacilityRecord::class, 'f')
      ->where(self::ORGANIZATION_PREDICATE)
      ->andWhere('IDENTITY(f.parentFacility) IN (:parentIds)')
      ->setParameter('organization', $organization)
      ->setParameter('parentIds', $parentIdValues)
      ->groupBy('f.parentFacility');

    if (!$includeArchived) {
      $queryBuilder
        ->andWhere('f.status = :activeStatus')
        ->setParameter('activeStatus', FacilityStatus::ACTIVE->value);
    }

    /** @var list<array{parentId: string|null, childCount: int|string}> $rows */
    $rows = $queryBuilder->getQuery()->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      // @codeCoverageIgnoreStart
      // Unreachable: the query filters on IDENTITY(f.parentFacility) IN (:parentIds)
      // and groups by that column. In SQL, NULL IN (...) is never true, so a root
      // facility cannot appear in these rows.
      if (null === $row['parentId']) {
        continue;
      }
      // @codeCoverageIgnoreEnd

      $counts[(string) $row['parentId']] = (int) $row['childCount'];
    }

    return $counts;
  }

  /**
   * Method findDescendants.
   *
   * Executes the find descendants operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param FacilityId $facilityId the facility id value
   * @param bool $includeArchived the include archived value
   * @param ?string $search the search value
   * @param Sorting $sorting the sorting value
   *
   * @return list<Facility> the find descendants result
   */
  public function findDescendants(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ): array {
    $descendantIds = $this->descendantIds($organizationId, (string) $facilityId);
    if ([] === $descendantIds) {
      return [];
    }

    /** @var list<FacilityRecord> $records */
    $records = $this->repository->findBy(['id' => $descendantIds]);

    $filtered = [];
    foreach ($records as $record) {
      // Archived facilities are traversed so their live descendants are reached,
      // but excluded from the result unless explicitly requested.
      if (!$includeArchived && FacilityStatus::ARCHIVED->value === $record->status) {
        continue;
      }

      if (!$this->matchesSearch($record, $search)) {
        continue;
      }

      $filtered[] = $record;
    }

    $this->sortRecords($filtered, $sorting);

    return array_map(
      static fn (FacilityRecord $record): Facility => FacilityMapper::toDomain($record),
      $filtered,
    );
  }

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
        AND plan_geometry ->> 'attachmentId' = :attachmentId
      SQL;

    /** @var list<array{id: string, status: string, type: string, name: string, plan_geometry: string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'rootId' => (string) $rootFacilityId,
      'organizationId' => (string) $organizationId,
      'published' => 'published',
      'attachmentId' => $attachmentId,
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
   *   planGeometry: ?array{attachmentId: string, points: list<array{0: float, 1: float}>},
   *   primaryPlanAttachmentId: ?string,
   *   primaryPlanImageWidth: ?int,
   *   primaryPlanImageHeight: ?int,
   * }> the building's floors, in render order
   */
  public function findBuildingFloors(
    FacilityOrganizationId $organizationId,
    FacilityId $buildingId,
  ): array {
    $sql = <<<'SQL'
      SELECT
          floor.id,
          floor.name,
          floor.status,
          floor.level_index,
          floor.plan_geometry,
          plan.id AS primary_plan_attachment_id,
          plan.image_width AS primary_plan_image_width,
          plan.image_height AS primary_plan_image_height
      FROM facilities floor
      LEFT JOIN facility_attachments plan
          ON plan.facility_id = floor.id
          AND plan.kind = 'floor_plan'
          AND plan.is_primary_plan
      WHERE floor.parent_facility_id = :buildingId
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
     *   plan_geometry: ?string,
     *   primary_plan_attachment_id: ?string,
     *   primary_plan_image_width: ?string,
     *   primary_plan_image_height: ?string,
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
        'planGeometry' => $planGeometry,
        'primaryPlanAttachmentId' => $row['primary_plan_attachment_id'],
        'primaryPlanImageWidth' => null !== $row['primary_plan_image_width'] ? (int) $row['primary_plan_image_width'] : null,
        'primaryPlanImageHeight' => null !== $row['primary_plan_image_height'] ? (int) $row['primary_plan_image_height'] : null,
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
      )
      SELECT floor_id, id, parent_facility_id, name, type, status, plan_geometry
      FROM subtree
      WHERE id <> floor_id
        AND type IN ('zone', 'area')
        AND plan_geometry IS NOT NULL
        AND plan_geometry ->> 'attachmentId' = attachment_id
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

  // #region Methods
  protected function organizationReference(FacilityOrganizationId $organizationId): OrganizationRecord
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return $organization;
  }

  protected function applyFacilitySearch(QueryBuilder $queryBuilder, ?string $search): void
  {
    TrigramSearchExpression::apply(
      $queryBuilder,
      'search',
      $search,
      'f.name',
      'f.type',
      'f.code',
      'f.status',
      'f.address',
    );
  }

  /**
   * Method isOrganizationConstraintViolation.
   *
   * Recognises the organization foreign key by name. Driver messages are a
   * persistence concern and must not reach the Application layer, so the
   * translation lives here rather than in a handler.
   *
   * @since 1.0.0
   *
   * @param ForeignKeyConstraintViolationException $exception the driver failure
   *
   * @return bool true when the organization foreign key caused the failure
   */
  private function isOrganizationConstraintViolation(ForeignKeyConstraintViolationException $exception): bool
  {
    $message = mb_strtolower($exception->getMessage());

    return str_contains($message, 'fk_facility_organization')
      || (str_contains($message, 'facilities') && str_contains($message, 'organization'));
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
  private function descendantIds(FacilityOrganizationId $organizationId, string $rootId): array
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
   * Method createChildQueryBuilder.
   *
   * Executes the create child query builder operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param FacilityId $facilityId the facility id value
   * @param bool $includeArchived the include archived value
   * @param ?string $search the search value
   *
   * @return QueryBuilder the create child query builder result
   */
  private function createChildQueryBuilder(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived,
    ?string $search,
  ): QueryBuilder {
    return $this->createListQueryBuilder(
      $organizationId,
      $includeArchived,
      new FacilityListCriteria(parentFacilityId: (string) $facilityId, search: $search),
    );
  }

  // #endregion
}
