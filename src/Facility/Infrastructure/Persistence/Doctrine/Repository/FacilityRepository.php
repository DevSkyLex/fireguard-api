<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function addcslashes;
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

  public function findChildren(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ): array {
    /** @var list<FacilityRecord> $records */
    $records = $this->createChildQueryBuilder($organizationId, $facilityId, $includeArchived, $search)
      ->orderBy($this->resolveSortField($sorting->field), strtoupper($sorting->direction->value))
      ->addOrderBy('f.id', 'ASC')
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (FacilityRecord $record): Facility => FacilityMapper::toDomain($record),
      $records,
    );
  }

  public function findDescendants(
    FacilityOrganizationId $organizationId,
    FacilityId $facilityId,
    bool $includeArchived = false,
    ?string $search = null,
    Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ): array {
    $pendingParentIds = [(string) $facilityId];
    $records = [];
    $seen = [];

    while ([] !== $pendingParentIds) {
      $nextParentIds = [];

      foreach ($pendingParentIds as $parentId) {
        $children = $this->findChildRecords($organizationId, $parentId);

        foreach ($children as $child) {
          if (isset($seen[$child->id])) {
            continue;
          }

          $seen[$child->id] = true;
          $nextParentIds[] = $child->id;

          if (!$includeArchived && FacilityStatus::ARCHIVED->value === $child->status) {
            continue;
          }

          if (!$this->matchesSearch($child, $search)) {
            continue;
          }

          $records[] = $child;
        }
      }

      $pendingParentIds = $nextParentIds;
    }

    $this->sortRecords($records, $sorting);

    return array_map(
      static fn (FacilityRecord $record): Facility => FacilityMapper::toDomain($record),
      $records,
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
  ): int {
    return (int) $this->createListQueryBuilder(
      $organizationId,
      $includeArchived,
      $type,
      $status,
      $parentFacilityId,
      $code,
      $search,
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

  private function createListQueryBuilder(
    FacilityOrganizationId $organizationId,
    bool $includeArchived,
    ?string $type,
    ?string $status,
    ?string $parentFacilityId,
    ?string $code,
    ?string $search,
  ): QueryBuilder {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('f')
      ->from(FacilityRecord::class, 'f')
      ->where('f.organization = :organization')
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

    if (null !== $parentFacilityId) {
      $queryBuilder
        ->andWhere('IDENTITY(f.parentFacility) = :parentFacilityId')
        ->setParameter('parentFacilityId', $parentFacilityId);
    }

    if (null !== $code) {
      $queryBuilder
        ->andWhere('f.code = :code')
        ->setParameter('code', $code);
    }

    if (null !== $search && '' !== $search) {
      $normalizedSearch = '%' . addcslashes(mb_strtolower($search), '%_') . '%';

      $queryBuilder
        ->andWhere('(
          LOWER(f.name) LIKE :search OR
          LOWER(f.type) LIKE :search OR
          LOWER(COALESCE(f.code, \'\')) LIKE :search OR
          LOWER(f.status) LIKE :search OR
          LOWER(COALESCE(f.address, \'\')) LIKE :search
        )')
        ->setParameter('search', $normalizedSearch);
    }

    return $queryBuilder;
  }

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
   * @return list<FacilityRecord>
   */
  private function findChildRecords(FacilityOrganizationId $organizationId, string $parentId): array
  {
    /** @var list<FacilityRecord> $records */
    $records = $this->createListQueryBuilder(
      $organizationId,
      true,
      null,
      null,
      $parentId,
      null,
      null,
    )
      ->getQuery()
      ->getResult();

    return $records;
  }

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
        'code' => ($left->code ?? '') <=> ($right->code ?? ''),
        default => $left->name <=> $right->name,
      };

      if (0 === $comparison) {
        return $direction * ($left->id <=> $right->id);
      }

      return $direction * $comparison;
    });
  }

  private function resolveSortField(string $field): string
  {
    return match ($field) {
      'type' => 'f.type',
      'status' => 'f.status',
      'createdAt' => 'f.createdAt',
      'code' => 'f.code',
      default => 'f.name',
    };
  }
  // #endregion
}
