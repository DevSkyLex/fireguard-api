<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\QueryBuilder;
use Facility\Application\Contract\Facility\FacilityListCriteria;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\{FacilityMapper, FacilityPersistenceExceptionMapper};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function array_map;
use function strtoupper;

/**
 * Repository FacilityRepository.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityRepository extends FacilityOrganizationQueryRepository implements FacilityRepositoryPort
{
  // #region Methods
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
      $existing->elevationMeters = $record->elevationMeters;
      $existing->heightMeters = $record->heightMeters;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    try {
      $this->entityManager->flush();
    } catch (ForeignKeyConstraintViolationException $exception) {
      throw FacilityPersistenceExceptionMapper::organizationConstraint($exception);
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

  /**
   * Method findPublishedById
   *
   * Finds and maps a facility only when its record is published.
   *
   * @access public
   *
   * @param FacilityId $id identifier of the facility to look up
   *
   * @return Facility|null published facility, or null when missing or not published
   */
  public function findPublishedById(FacilityId $id): ?Facility
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord || 'published' !== $record->recordStatus) {
      return null;
    }

    return FacilityMapper::toDomain($record);
  }

  /**
   * {@inheritDoc}
   */
  public function findProjectionContextsByFacilityIds(FacilityOrganizationId $organizationId, array $facilityIds): array
  {
    if ([] === $facilityIds) {
      return [];
    }
    /** @var list<array{id: string, record_status: string, intervention_id: ?string, revision: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery(
      'SELECT id, record_status, intervention_id, revision FROM facilities WHERE organization_id = :organizationId AND id IN (:ids)',
      ['organizationId' => (string) $organizationId, 'ids' => $facilityIds],
      ['ids' => ArrayParameterType::STRING],
    )->fetchAllAssociative();
    $contexts = [];
    foreach ($rows as $row) {
      $contexts[$row['id']] = ['recordStatus' => $row['record_status'], 'interventionId' => $row['intervention_id'], 'revision' => (int) $row['revision']];
    }

    return $contexts;
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
    $records = $this->createListQueryBuilder($organizationId, $includeArchived, new FacilityListCriteria(parentFacilityId: (string) $facilityId, search: $search))
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
    return (int) $this->createListQueryBuilder($organizationId, $includeArchived, new FacilityListCriteria(parentFacilityId: (string) $facilityId, search: $search))
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
   * @param ?int $limit optional page size; null preserves the bulk read
   * @param int $offset the zero-based page offset
   *
   * @return list<Facility> the find descendants result
   */
  public function findDescendants(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    ?int $limit = null,
    int $offset = 0,
  ): array {
    $descendantIds = new FacilityHierarchyQueryRepository($this->entityManager)->descendantIds($organizationId, (string) $facilityId);
    if ([] === $descendantIds) {
      return [];
    }

    $builder = $this->createListQueryBuilder($organizationId, $includeArchived, new FacilityListCriteria(search: $search))
      ->andWhere('f.id IN (:descendantIds)')
      ->setParameter('descendantIds', $descendantIds)
      ->addSelect("COALESCE(f.code, '') AS HIDDEN descendantCodeSort")
      ->orderBy('code' === $sorting->field ? 'descendantCodeSort' : $this->resolveSortField($sorting->field), strtoupper($sorting->direction->value))
      ->addOrderBy('f.id', 'ASC');

    if (null !== $limit) {
      $builder->setFirstResult($offset)->setMaxResults($limit);
    }

    /** @var list<FacilityRecord> $records */
    $records = $builder->getQuery()->getResult();

    return array_map(
      static fn (FacilityRecord $record): Facility => FacilityMapper::toDomain($record),
      $records,
    );
  }

  /**
   * {@inheritDoc}
   */
  public function countDescendants(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
  ): int {
    $descendantIds = new FacilityHierarchyQueryRepository($this->entityManager)->descendantIds($organizationId, (string) $facilityId);
    if ([] === $descendantIds) {
      return 0;
    }

    return (int) $this->createListQueryBuilder($organizationId, $includeArchived, new FacilityListCriteria(search: $search))
      ->select('COUNT(f.id)')
      ->andWhere('f.id IN (:descendantIds)')
      ->setParameter('descendantIds', $descendantIds)
      ->getQuery()
      ->getSingleScalarResult();
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->findAncestors($facilityId);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->findAncestorsByFacilityIds($organizationId, $facilityIds);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->hasActiveDescendants($organizationId, $facilityId);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->findZonesForPlanAttachment($organizationId, $rootFacilityId, $attachmentId);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->findBuildingFloors($organizationId, $buildingId);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->findRoomsForFloors($organizationId, $floorPlanBindings);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->depthOf($facilityId);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->subtreeHeight($facilityId);
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
    return new FacilityHierarchyQueryRepository($this->entityManager)->findFacilityBindingsForFloors($organizationId, $floorIds);
  }

  /**
   * Method applyFacilitySearch
   *
   * Adds the supported trigram-search fields to a facility query.
   *
   * @access protected
   *
   * @param QueryBuilder $queryBuilder query to constrain
   * @param string|null $search search term, or null when no search was requested
   *
   * @return void
   */
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
   * Method organizationReference
   *
   * Returns a Doctrine reference for the organization without loading its state.
   *
   * @access protected
   *
   * @param FacilityOrganizationId $organizationId identifier of the organization
   *
   * @return OrganizationRecord organization reference used by facility queries
   */
  protected function organizationReference(FacilityOrganizationId $organizationId): OrganizationRecord
  {
    /** @var OrganizationRecord */
    return $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
  }

  // #endregion
}
