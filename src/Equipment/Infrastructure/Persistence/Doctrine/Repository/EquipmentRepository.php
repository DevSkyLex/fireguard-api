<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\EquipmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Exception;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use RuntimeException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function array_map;
use function str_contains;
use function strtolower;
use function strtoupper;

/**
 * Repository EquipmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentRepository implements EquipmentRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<EquipmentRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
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
    $this->repository = $this->entityManager->getRepository(EquipmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(Equipment $equipment): void
  {
    $record = EquipmentMapper::toRecord($equipment);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $equipment->organizationId());
    $record->organization = $organization;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof EquipmentRecord) {
      $existing->organization = $organization;
      $existing->facilityId = $record->facilityId;
      $existing->type = $record->type;
      $existing->subType = $record->subType;
      $existing->brand = $record->brand;
      $existing->model = $record->model;
      $existing->serialNumber = $record->serialNumber;
      $existing->locationLabel = $record->locationLabel;
      $existing->status = $record->status;
      $existing->installedAt = $record->installedAt;
      $existing->commissionedAt = $record->commissionedAt;
      $existing->planPosition = $record->planPosition;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    try {
      $this->entityManager->flush();
    } catch (Throwable $exception) {
      if ($this->isDuplicateSerialNumberViolation($exception)) {
        throw EquipmentSerialNumberAlreadyExistsException::withSerialNumber($equipment->serialNumber() ?? '');
      }

      throw $exception;
    }
  }

  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(EquipmentId $id): ?Equipment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof EquipmentRecord) {
      return null;
    }

    return EquipmentMapper::toDomain($record);
  }

  public function findPublishedById(EquipmentId $id): ?Equipment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof EquipmentRecord || 'published' !== $record->recordStatus) {
      return null;
    }

    return EquipmentMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationId.
   *
   * @since 1.0.0
   */
  public function findByOrganizationId(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId = null,
    ?string $type = null,
    ?string $status = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $subType = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    $queryBuilder = $this->createListQueryBuilder(
      $organizationId,
      $facilityId,
      $type,
      $status,
      $brand,
      $model,
      $subType,
      $search,
    );

    $sortField = match ($sorting->field) {
      'type' => 'e.type',
      'status' => 'e.status',
      'brand' => 'e.brand',
      'model' => 'e.model',
      'updatedAt' => 'e.updatedAt',
      default => 'e.createdAt',
    };

    /** @var list<EquipmentRecord> $records */
    $records = $queryBuilder
      ->orderBy($sortField, strtoupper($sorting->direction->value))
      ->addOrderBy('e.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (EquipmentRecord $record): Equipment => EquipmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByOrganizationId.
   *
   * @since 1.0.0
   */
  public function countByOrganizationId(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId = null,
    ?string $type = null,
    ?string $status = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $subType = null,
    ?string $search = null,
  ): int {
    return (int) $this->createListQueryBuilder(
      $organizationId,
      $facilityId,
      $type,
      $status,
      $brand,
      $model,
      $subType,
      $search,
    )
      ->select('COUNT(e.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Method countOverviewByOrganizationId.
   *
   * Executes the count overview by organization id operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization id value
   * @param ?string $type the type value
   * @param ?string $status the status value
   *
   * @return array{total: int, in_stock: int, operational: int, under_maintenance: int, decommissioned: int} the count overview by organization id result
   */
  public function countOverviewByOrganizationId(EquipmentOrganizationId $organizationId, ?string $type = null, ?string $status = null): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select(
        'COUNT(e.id) AS total',
        'COALESCE(SUM(CASE WHEN e.status = :inStockStatus THEN 1 ELSE 0 END), 0) AS inStock',
        'COALESCE(SUM(CASE WHEN e.status = :operationalStatus THEN 1 ELSE 0 END), 0) AS operational',
        'COALESCE(SUM(CASE WHEN e.status = :underMaintenanceStatus THEN 1 ELSE 0 END), 0) AS underMaintenance',
        'COALESCE(SUM(CASE WHEN e.status = :decommissionedStatus THEN 1 ELSE 0 END), 0) AS decommissioned',
      )
      ->from(EquipmentRecord::class, 'e')
      ->where('e.organization = :organization')
      ->setParameter('organization', $organization)
      ->setParameter('inStockStatus', 'in_stock')
      ->setParameter('operationalStatus', 'operational')
      ->setParameter('underMaintenanceStatus', 'under_maintenance')
      ->setParameter('decommissionedStatus', 'decommissioned');

    if (null !== $type) {
      $queryBuilder->andWhere('e.type = :type')->setParameter('type', $type);
    }
    if (null !== $status) {
      $queryBuilder->andWhere('e.status = :status')->setParameter('status', $status);
    }

    /** @var array{total?: int|string|null, inStock?: int|string|null, operational?: int|string|null, underMaintenance?: int|string|null, decommissioned?: int|string|null} $row */
    $row = $queryBuilder->getQuery()->getSingleResult();

    return [
      'total' => (int) ($row['total'] ?? 0),
      'in_stock' => (int) ($row['inStock'] ?? 0),
      'operational' => (int) ($row['operational'] ?? 0),
      'under_maintenance' => (int) ($row['underMaintenance'] ?? 0),
      'decommissioned' => (int) ($row['decommissioned'] ?? 0),
    ];
  }

  /**
   * Method countByStatusForOrganizationId.
   *
   * Executes the count by status for organization id operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization id value
   *
   * @return array<string, int> map of status => count
   */
  public function countByStatusForOrganizationId(EquipmentOrganizationId $organizationId): array
  {
    /** @var list<array{status: string, equipmentCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('e.status AS status, COUNT(e.id) AS equipmentCount')
      ->groupBy('e.status')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['status']] = (int) $row['equipmentCount'];
    }

    return $counts;
  }

  /**
   * Method countByTypeForOrganizationId.
   *
   * Executes the count by type for organization id operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization id value
   *
   * @return array<string, int> map of type => count
   */
  public function countByTypeForOrganizationId(EquipmentOrganizationId $organizationId): array
  {
    /** @var list<array{type: string, equipmentCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('e.type AS type, COUNT(e.id) AS equipmentCount')
      ->groupBy('e.type')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['type']] = (int) $row['equipmentCount'];
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
   * @param EquipmentOrganizationId $organizationId the organization id value
   * @param string $createdAtFrom the created at from value
   * @param string $createdAtTo the created at to value
   * @param ?string $timeZone the time zone value
   * @param ?string $type the type value
   * @param ?string $status the status value
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByCreatedDayForOrganizationId(
    EquipmentOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
    ?string $status = null,
  ): array {
    $bucketTimeZone = $this->resolveBucketTimeZone($timeZone, $createdAtFrom);
    $storageTimeZone = $this->resolveStorageTimeZone();
    $sql = <<<'SQL'
        SELECT
          TO_CHAR(((created_at AT TIME ZONE :storageTimeZone) AT TIME ZONE :bucketTimeZone), 'YYYY-MM-DD') AS bucket,
          COUNT(*) AS equipment_count
        FROM equipment
        WHERE organization_id = :organizationId
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

    if (null !== $status) {
      $sql .= "\n  AND status = :status";
      $parameters['status'] = $status;
    }

    $sql .= "\nGROUP BY 1\nORDER BY 1 ASC";

    /** @var list<array{bucket: string, equipment_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $parameters)->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['bucket']] = (int) $row['equipment_count'];
    }

    return $counts;
  }

  /**
   * Method createListQueryBuilder.
   *
   * Executes the create list query builder operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization id value
   * @param ?string $facilityId the facility id value
   * @param ?string $type the type value
   * @param ?string $status the status value
   * @param ?string $brand the brand value
   * @param ?string $model the model value
   * @param ?string $subType the sub type value
   * @param ?string $search the search value
   *
   * @return QueryBuilder the create list query builder result
   */
  private function createListQueryBuilder(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId,
    ?string $type,
    ?string $status,
    ?string $brand,
    ?string $model,
    ?string $subType,
    ?string $search,
  ): QueryBuilder {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('e')
      ->from(EquipmentRecord::class, 'e')
      ->where('e.organization = :organization')
      ->andWhere('e.recordStatus = :publishedRecordStatus')
      ->setParameter('publishedRecordStatus', 'published')
      ->setParameter('organization', $organization);

    if (null !== $facilityId) {
      $queryBuilder
        ->andWhere('e.facilityId = :facilityId')
        ->setParameter('facilityId', $facilityId);
    }

    if (null !== $type) {
      $queryBuilder
        ->andWhere('e.type = :type')
        ->setParameter('type', $type);
    }

    if (null !== $status) {
      $queryBuilder
        ->andWhere('e.status = :status')
        ->setParameter('status', $status);
    }

    if (null !== $brand) {
      $queryBuilder
        ->andWhere('e.brand = :brand')
        ->setParameter('brand', $brand);
    }

    if (null !== $model) {
      $queryBuilder
        ->andWhere('e.model = :model')
        ->setParameter('model', $model);
    }

    if (null !== $subType) {
      $queryBuilder
        ->andWhere('e.subType = :subType')
        ->setParameter('subType', $subType);
    }

    TrigramSearchExpression::apply(
      $queryBuilder,
      'search',
      $search,
      'e.type',
      'e.subType',
      'e.brand',
      'e.model',
      'e.serialNumber',
      'e.status',
      'e.locationLabel',
    );

    return $queryBuilder;
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

  /**
   * Method isDuplicateSerialNumberViolation.
   *
   * Executes the is duplicate serial number violation operation.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception value
   *
   * @return bool the is duplicate serial number violation result
   */
  private function isDuplicateSerialNumberViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'uniq_equipment_organization_serial') || (str_contains($message, 'equipment') && str_contains($message, 'serial'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }

  // #endregion
}
