<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Exception;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use RuntimeException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_map;
use function count;
use function mb_strtolower;
use function str_contains;
use function strtoupper;
use function usort;

/**
 * Repository FacilityRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityRepository implements FacilityRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<FacilityRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')]
    private string $storageTimeZone = 'UTC',
  ) {
    $this->repository = $this->entityManager->getRepository(FacilityRecord::class);
  }
  // #endregion

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
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
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
      ->where('f.organization = :organization')
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
      if (null === $row['parentId']) {
        continue;
      }

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
   * Method countByOrganizationId.
   *
   * Counts facilities for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param bool $includeArchived whether archived facilities are included by default when no explicit status filter is provided
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   * @param ?string $parentFacilityId optional parent facility filter
   * @param ?string $code optional exact code filter
   * @param ?string $search optional text search applied before counting
   *
   * @return int the facilities count
   */
  public function countByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    ?string $type = null,
    ?string $status = null,
    ?string $parentFacilityId = null,
    ?string $code = null,
    ?string $search = null,
    bool $rootsOnly = false,
  ): int {
    return (int) $this->createListQueryBuilder(
      $organizationId,
      $includeArchived,
      $type,
      $status,
      $parentFacilityId,
      $code,
      $search,
      $rootsOnly,
    )
      ->select('COUNT(f.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Method countActiveByOrganizationId.
   *
   * Counts active (non-archived) facilities belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return int the active facility count
   */
  public function countActiveByOrganizationId(FacilityOrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->repository->count([
      'organization' => $organization,
      'status' => FacilityStatus::ACTIVE->value,
    ]);
  }

  /**
   * Method countOverviewByOrganizationId.
   *
   * Executes the count overview by organization id operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param ?string $type the type value
   *
   * @return array{total: int, active: int} the count overview by organization id result
   */
  public function countOverviewByOrganizationId(FacilityOrganizationId $organizationId, ?string $type = null): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select(
        'COUNT(f.id) AS total',
        'COALESCE(SUM(CASE WHEN f.status = :activeStatus THEN 1 ELSE 0 END), 0) AS active',
      )
      ->from(FacilityRecord::class, 'f')
      ->where('f.organization = :organization')
      ->setParameter('organization', $organization)
      ->setParameter('activeStatus', FacilityStatus::ACTIVE->value);

    if (null !== $type) {
      $queryBuilder->andWhere('f.type = :type')->setParameter('type', $type);
    }

    /** @var array{total?: int|string|null, active?: int|string|null} $row */
    $row = $queryBuilder->getQuery()->getSingleResult();

    return [
      'total' => (int) ($row['total'] ?? 0),
      'active' => (int) ($row['active'] ?? 0),
    ];
  }

  /**
   * Method countByTypeForOrganizationId.
   *
   * Executes the count by type for organization id operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param bool $includeArchived the include archived value
   *
   * @return array<string, int> map of type => count
   */
  public function countByTypeForOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
  ): array {
    /** @var list<array{type: string, facilityCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      $includeArchived,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('f.type AS type, COUNT(f.id) AS facilityCount')
      ->groupBy('f.type')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['type']] = (int) $row['facilityCount'];
    }

    return $counts;
  }

  /**
   * Method countByCreatedDayForOrganizationId.
   *
   * Executes the count by created day for organization id operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param string $createdAtFrom the created at from value
   * @param string $createdAtTo the created at to value
   * @param ?string $timeZone the time zone value
   * @param ?string $type the type value
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByCreatedDayForOrganizationId(
    FacilityOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
  ): array {
    $bucketTimeZone = $this->resolveBucketTimeZone($timeZone, $createdAtFrom);
    if ('postgresql' === $this->entityManager->getConnection()->getDatabasePlatform()->getName()) {
      return $this->countByCreatedDayForOrganizationIdPostgreSql(
        organizationId: $organizationId,
        createdAtFrom: $createdAtFrom,
        createdAtTo: $createdAtTo,
        bucketTimeZone: $bucketTimeZone,
        type: $type,
      );
    }

    /** @var list<array{createdAt: DateTimeImmutable}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      true,
      $type,
      null,
      null,
      null,
      null,
    )
      ->select('f.createdAt AS createdAt')
      ->andWhere('f.createdAt >= :createdAtFrom')
      ->andWhere('f.createdAt <= :createdAtTo')
      ->setParameter('createdAtFrom', $this->normalizeTimestampToStorageDateTime($createdAtFrom), Types::DATETIME_IMMUTABLE)
      ->setParameter('createdAtTo', $this->normalizeTimestampToStorageDateTime($createdAtTo), Types::DATETIME_IMMUTABLE)
      ->orderBy('f.createdAt', 'ASC')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $bucket = $this->reinterpretStorageDateTime($row['createdAt'])->setTimezone($bucketTimeZone)->format('Y-m-d');
      $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }

    return $counts;
  }

  /**
   * Method getFacilityNamesByIds.
   *
   * Resolves facility display names for a bounded set of identifiers,
   * scoped to the organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> map of facilityId => name
   */
  public function getFacilityNamesByIds(FacilityOrganizationId $organizationId, array $facilityIds): array
  {
    if ([] === $facilityIds) {
      return [];
    }

    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    /** @var list<array{id: string, name: string}> $rows */
    $rows = $this->repository->createQueryBuilder('f')
      ->select('f.id AS id, f.name AS name')
      ->where('f.organization = :organization')
      ->andWhere('f.id IN (:facilityIds)')
      ->setParameter('organization', $organization)
      ->setParameter('facilityIds', $facilityIds)
      ->getQuery()
      ->getArrayResult();

    $names = [];
    foreach ($rows as $row) {
      $names[(string) $row['id']] = (string) $row['name'];
    }

    return $names;
  }

  /**
   * Method findByOrganizationId.
   *
   * Lists facilities by organization identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return list<Facility> the facilities
   */
  public function findByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    ?string $type = null,
    ?string $status = null,
    ?string $parentFacilityId = null,
    ?string $code = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
    bool $rootsOnly = false,
  ): array {
    /** @var list<FacilityRecord> $records */
    $records = $this->createListQueryBuilder(
      $organizationId,
      $includeArchived,
      $type,
      $status,
      $parentFacilityId,
      $code,
      $search,
      $rootsOnly,
    )
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

    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->executeQuery($sql, [
      'rootId' => $rootId,
      'organizationId' => (string) $organizationId,
      'published' => 'published',
    ])->fetchFirstColumn();

    return $ids;
  }

  /**
   * Method createListQueryBuilder.
   *
   * Executes the create list query builder operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param bool $includeArchived the include archived value
   * @param ?string $type the type value
   * @param ?string $status the status value
   * @param ?string $parentFacilityId the parent facility id value
   * @param ?string $code the code value
   * @param ?string $search the search value
   * @param bool $rootsOnly the roots only value
   *
   * @return QueryBuilder the create list query builder result
   */
  private function createListQueryBuilder(
    FacilityOrganizationId $organizationId,
    bool $includeArchived,
    ?string $type,
    ?string $status,
    ?string $parentFacilityId,
    ?string $code,
    ?string $search,
    bool $rootsOnly = false,
  ): QueryBuilder {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('f')
      ->from(FacilityRecord::class, 'f')
      ->where('f.organization = :organization')
      ->andWhere('f.recordStatus = :publishedRecordStatus')
      ->setParameter('publishedRecordStatus', 'published')
      ->setParameter('organization', $organization);

    if (null === $status && !$includeArchived) {
      $queryBuilder
        ->andWhere('f.status = :activeStatus')
        ->setParameter('activeStatus', FacilityStatus::ACTIVE->value);
    }

    if (null !== $type) {
      $queryBuilder
        ->andWhere('f.type = :type')
        ->setParameter('type', $type);
    }

    if (null !== $status) {
      $queryBuilder
        ->andWhere('f.status = :status')
        ->setParameter('status', $status);
    }

    if ($rootsOnly) {
      $queryBuilder->andWhere('f.parentFacility IS NULL');
    } elseif (null !== $parentFacilityId) {
      $queryBuilder
        ->andWhere('IDENTITY(f.parentFacility) = :parentFacilityId')
        ->setParameter('parentFacilityId', $parentFacilityId);
    }

    if (null !== $code) {
      $queryBuilder
        ->andWhere('f.code = :code')
        ->setParameter('code', $code);
    }

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

    return $queryBuilder;
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
      null,
      null,
      (string) $facilityId,
      null,
      $search,
    );
  }

  /**
   * Method matchesSearch.
   *
   * Executes the matches search operation.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the record value
   * @param ?string $search the search value
   *
   * @return bool the matches search result
   */
  private function matchesSearch(FacilityRecord $record, ?string $search): bool
  {
    if (null === $search || '' === $search) {
      return true;
    }

    $normalizedSearch = mb_strtolower($search);
    $haystack = [
      mb_strtolower($record->name),
      mb_strtolower($record->type),
      mb_strtolower($record->status),
      mb_strtolower($record->code ?? ''),
      mb_strtolower($record->address ?? ''),
    ];

    foreach ($haystack as $value) {
      if (str_contains($value, $normalizedSearch)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param list<FacilityRecord> $records
   */
  private function sortRecords(array &$records, Sorting $sorting): void
  {
    $direction = SortDirection::DESC === $sorting->direction ? -1 : 1;

    usort($records, function (FacilityRecord $left, FacilityRecord $right) use ($direction, $sorting): int {
      $comparison = match ($sorting->field) {
        'type' => $left->type <=> $right->type,
        'status' => $left->status <=> $right->status,
        'createdAt' => $left->createdAt <=> $right->createdAt,
        'updatedAt' => $left->updatedAt <=> $right->updatedAt,
        'code' => ($left->code ?? '') <=> ($right->code ?? ''),
        default => $left->name <=> $right->name,
      };

      if (0 === $comparison) {
        return $direction * ($left->id <=> $right->id);
      }

      return $direction * $comparison;
    });
  }

  /**
   * Method resolveSortField.
   *
   * Executes the resolve sort field operation.
   *
   * @since 1.0.0
   *
   * @param string $field the field value
   *
   * @return string the resolve sort field result
   */
  private function resolveSortField(string $field): string
  {
    return match ($field) {
      'type' => 'f.type',
      'status' => 'f.status',
      'createdAt' => 'f.createdAt',
      'updatedAt' => 'f.updatedAt',
      'code' => 'f.code',
      default => 'f.name',
    };
  }

  /**
   * @return array<string, int>
   */
  private function countByCreatedDayForOrganizationIdPostgreSql(
    FacilityOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    DateTimeZone $bucketTimeZone,
    ?string $type = null,
  ): array {
    $storageTimeZone = $this->resolveStorageTimeZone();
    $sql = <<<'SQL'
        SELECT
          TO_CHAR(((created_at AT TIME ZONE :storageTimeZone) AT TIME ZONE :bucketTimeZone), 'YYYY-MM-DD') AS bucket,
          COUNT(*) AS facility_count
        FROM facilities
        WHERE organization_id = :organizationId
          AND record_status = 'published'
          AND created_at >= :createdAtFrom
          AND created_at <= :createdAtTo
      SQL;
    $parameters = [
      'storageTimeZone' => $storageTimeZone->getName(),
      'bucketTimeZone' => $bucketTimeZone->getName(),
      'organizationId' => (string) $organizationId,
      'createdAtFrom' => $this->normalizeTimestampForStorageTimeZone($createdAtFrom, $storageTimeZone),
      'createdAtTo' => $this->normalizeTimestampForStorageTimeZone($createdAtTo, $storageTimeZone),
    ];

    if (null !== $type) {
      $sql .= "\n  AND type = :type";
      $parameters['type'] = $type;
    }

    $sql .= "\nGROUP BY 1\nORDER BY 1 ASC";

    /** @var list<array{bucket: string, facility_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $parameters)->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['bucket']] = (int) $row['facility_count'];
    }

    return $counts;
  }

  /**
   * Method resolveBucketTimeZone.
   *
   * Executes the resolve bucket time zone operation.
   *
   * @since 1.0.0
   *
   * @param ?string $timeZone the time zone value
   * @param string $lowerBound the lower bound value
   *
   * @return DateTimeZone the resolve bucket time zone result
   */
  private function resolveBucketTimeZone(?string $timeZone, string $lowerBound): DateTimeZone
  {
    if (null !== $timeZone && '' !== $timeZone) {
      return new DateTimeZone($timeZone);
    }

    return new DateTimeImmutable($lowerBound)->getTimezone();
  }

  /**
   * Method resolveStorageTimeZone.
   *
   * Executes the resolve storage time zone operation.
   *
   * @since 1.0.0
   *
   * @return DateTimeZone the resolve storage time zone result
   */
  private function resolveStorageTimeZone(): DateTimeZone
  {
    try {
      return new DateTimeZone($this->storageTimeZone);
    } catch (Exception $exception) {
      throw new RuntimeException('Invalid DATABASE_STORAGE_TIMEZONE configuration.', 0, $exception);
    }
  }

  /**
   * Method normalizeTimestampToStorageDateTime.
   *
   * Executes the normalize timestamp to storage date time operation.
   *
   * @since 1.0.0
   *
   * @param string $value the value value
   *
   * @return DateTimeImmutable the normalize timestamp to storage date time result
   */
  private function normalizeTimestampToStorageDateTime(string $value): DateTimeImmutable
  {
    return new DateTimeImmutable($value)
      ->setTimezone($this->resolveStorageTimeZone());
  }

  /**
   * Method reinterpretStorageDateTime.
   *
   * Executes the reinterpret storage date time operation.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $value the value value
   *
   * @return DateTimeImmutable the reinterpret storage date time result
   */
  private function reinterpretStorageDateTime(DateTimeImmutable $value): DateTimeImmutable
  {
    return DateTimeImmutable::createFromFormat(
      'Y-m-d H:i:s.u',
      $value->format('Y-m-d H:i:s.u'),
      $this->resolveStorageTimeZone(),
    ) ?: $value->setTimezone($this->resolveStorageTimeZone());
  }

  /**
   * Method normalizeTimestampForStorageTimeZone.
   *
   * Executes the normalize timestamp for storage time zone operation.
   *
   * @since 1.0.0
   *
   * @param string $value the value value
   * @param DateTimeZone $storageTimeZone the storage time zone value
   *
   * @return string the normalize timestamp for storage time zone result
   */
  private function normalizeTimestampForStorageTimeZone(string $value, DateTimeZone $storageTimeZone): string
  {
    return new DateTimeImmutable($value)
      ->setTimezone($storageTimeZone)
      ->format('Y-m-d H:i:s.u');
  }
  // #endregion
}
