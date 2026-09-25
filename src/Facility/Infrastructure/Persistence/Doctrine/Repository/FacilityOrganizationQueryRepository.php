<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Exception;
use Facility\Application\Contract\Facility\FacilityListCriteria;
use Facility\Application\Port\Outbound\FacilityOrganizationQueryPort;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityOrganizationId, FacilityStatus};
use Facility\Infrastructure\Exception\InvalidStorageTimeZoneException;
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_map;
use function mb_strtolower;
use function str_contains;
use function strtoupper;
use function usort;

/** Organization-scoped facility listings, labels and dashboard statistics. */
abstract readonly class FacilityOrganizationQueryRepository implements FacilityOrganizationQueryPort
{
  // #region Constants
  protected const string ORGANIZATION_PREDICATE = 'f.organization = :organization';
  // #endregion

  // #region Properties
  /**
   * @var EntityRepository<FacilityRecord>
   */
  protected EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the organization query repository shared by facility persistence.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    protected EntityManagerInterface $entityManager,
    #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')]
    private string $storageTimeZone = 'UTC',
  ) {
    $this->repository = $this->entityManager->getRepository(FacilityRecord::class);
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
   * @param FacilityListCriteria $criteria filters applied before counting
   *
   * @return int the facilities count
   */
  public function countByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    FacilityListCriteria $criteria = new FacilityListCriteria(),
  ): int {
    return (int) $this->createListQueryBuilder(
      $organizationId,
      $includeArchived,
      $criteria,
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
    $organization = $this->organizationReference($organizationId);

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
    $organization = $this->organizationReference($organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select(
        'COUNT(f.id) AS total',
        'COALESCE(SUM(CASE WHEN f.status = :activeStatus THEN 1 ELSE 0 END), 0) AS active',
      )
      ->from(FacilityRecord::class, 'f')
      ->where(self::ORGANIZATION_PREDICATE)
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
      new FacilityListCriteria(),
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

    $organization = $this->organizationReference($organizationId);

    /** @var list<array{id: string, name: string}> $rows */
    $rows = $this->repository->createQueryBuilder('f')
      ->select('f.id AS id, f.name AS name')
      ->where(self::ORGANIZATION_PREDICATE)
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
   * Method getFacilityCodesByIds.
   *
   * Resolves facility `code` values for a bounded set of identifiers, scoped
   * to the organization — mirrors {@see self::getFacilityNamesByIds()}.
   * Facilities with no code are simply absent from the returned map.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> map of facilityId => code
   */
  public function getFacilityCodesByIds(FacilityOrganizationId $organizationId, array $facilityIds): array
  {
    if ([] === $facilityIds) {
      return [];
    }

    $organization = $this->organizationReference($organizationId);

    /** @var list<array{id: string, code: ?string}> $rows */
    $rows = $this->repository->createQueryBuilder('f')
      ->select('f.id AS id, f.code AS code')
      ->where(self::ORGANIZATION_PREDICATE)
      ->andWhere('f.id IN (:facilityIds)')
      ->setParameter('organization', $organization)
      ->setParameter('facilityIds', $facilityIds)
      ->getQuery()
      ->getArrayResult();

    $codes = [];
    foreach ($rows as $row) {
      if (null === $row['code'] || '' === $row['code']) {
        continue;
      }
      $codes[(string) $row['id']] = (string) $row['code'];
    }

    return $codes;
  }

  /**
   * Method findByOrganizationId.
   *
   * Lists facilities by organization identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param FacilityListCriteria $criteria filters applied before pagination
   *
   * @return list<Facility> the facilities
   */
  public function findByOrganizationId(
    FacilityOrganizationId $organizationId,
    bool $includeArchived = false,
    FacilityListCriteria $criteria = new FacilityListCriteria(),
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    /** @var list<FacilityRecord> $records */
    $records = $this->createListQueryBuilder(
      $organizationId,
      $includeArchived,
      $criteria,
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
  // #endregion

  // #region Methods
  /**
   * Resolves the existing organization association without moving its ORM dependency into this query concern.
   */
  abstract protected function organizationReference(FacilityOrganizationId $organizationId): object;

  /**
   * Applies the facility search semantics shared with the existing repository.
   */
  abstract protected function applyFacilitySearch(QueryBuilder $queryBuilder, ?string $search): void;

  /**
   * Method createListQueryBuilder.
   *
   * Executes the create list query builder operation.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization id value
   * @param bool $includeArchived the include archived value
   * @param FacilityListCriteria $criteria the filters shared by list and count
   *
   * @return QueryBuilder the create list query builder result
   */
  protected function createListQueryBuilder(
    FacilityOrganizationId $organizationId,
    bool $includeArchived,
    FacilityListCriteria $criteria,
  ): QueryBuilder {
    $organization = $this->organizationReference($organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('f')
      ->from(FacilityRecord::class, 'f')
      ->where(self::ORGANIZATION_PREDICATE)
      ->andWhere('f.recordStatus = :publishedRecordStatus')
      ->setParameter('publishedRecordStatus', 'published')
      ->setParameter('organization', $organization);

    if (null === $criteria->status && !$includeArchived) {
      $queryBuilder
        ->andWhere('f.status = :activeStatus')
        ->setParameter('activeStatus', FacilityStatus::ACTIVE->value);
    }

    if (null !== $criteria->type) {
      $queryBuilder
        ->andWhere('f.type = :type')
        ->setParameter('type', $criteria->type);
    }

    if (null !== $criteria->status) {
      $queryBuilder
        ->andWhere('f.status = :status')
        ->setParameter('status', $criteria->status);
    }

    if ($criteria->rootsOnly) {
      $queryBuilder->andWhere('f.parentFacility IS NULL');
    } elseif (null !== $criteria->parentFacilityId) {
      $queryBuilder
        ->andWhere('IDENTITY(f.parentFacility) = :parentFacilityId')
        ->setParameter('parentFacilityId', $criteria->parentFacilityId);
    }

    if (null !== $criteria->code) {
      $queryBuilder
        ->andWhere('f.code = :code')
        ->setParameter('code', $criteria->code);
    }

    if (true === $criteria->hasCoordinates) {
      $queryBuilder
        ->andWhere('f.latitude IS NOT NULL')
        ->andWhere('f.longitude IS NOT NULL');
    } elseif (false === $criteria->hasCoordinates) {
      $queryBuilder
        ->andWhere('(f.latitude IS NULL OR f.longitude IS NULL)');
    }

    $this->applyFacilitySearch($queryBuilder, $criteria->search);

    return $queryBuilder;
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
  protected function matchesSearch(FacilityRecord $record, ?string $search): bool
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
  protected function sortRecords(array &$records, Sorting $sorting): void
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
  protected function resolveSortField(string $field): string
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
      throw new InvalidStorageTimeZoneException('Invalid DATABASE_STORAGE_TIMEZONE configuration.', 0, $exception);
    }
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
