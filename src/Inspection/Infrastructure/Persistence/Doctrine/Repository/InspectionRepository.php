<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Exception;
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use RuntimeException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_map;
use function strtoupper;

/**
 * Repository InspectionRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionRepository implements InspectionRepositoryPort
{
  /**
   * @var EntityRepository<InspectionRecord>
   */
  private EntityRepository $repository;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param string $storageTimeZone the storage time zone value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')]
    private string $storageTimeZone = 'UTC',
  ) {
    $this->repository = $this->entityManager->getRepository(InspectionRecord::class);
  }

  /**
   * Method save.
   *
   * Executes the save operation.
   *
   * @since 1.0.0
   *
   * @param Inspection $inspection the inspection value
   */
  public function save(Inspection $inspection): void
  {
    $record = $this->normalizeRecordDateTimesToStorage(InspectionMapper::toRecord($inspection));
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $inspection->organizationId());
    $record->organization = $organization;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof InspectionRecord) {
      $existing->organization = $organization;
      $existing->equipmentId = $record->equipmentId;
      $existing->facilityId = $record->facilityId;
      $existing->inspectorType = $record->inspectorType;
      $existing->inspectorName = $record->inspectorName;
      $existing->inspectorUserId = $record->inspectorUserId;
      $existing->inspectorOrganizationName = $record->inspectorOrganizationName;
      $existing->result = $record->result;
      $existing->status = $record->status;
      $existing->performedAt = $record->performedAt;
      $existing->checklistId = $record->checklistId;
      $existing->notes = $record->notes;
      $existing->signature = $record->signature;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method remove.
   *
   * Executes the remove operation.
   *
   * @since 1.0.0
   *
   * @param Inspection $inspection the inspection value
   */
  public function remove(Inspection $inspection): void
  {
    $record = $this->repository->find((string) $inspection->id());

    if (!$record instanceof InspectionRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Executes the find by id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionId $id the id value
   *
   * @return ?Inspection the find by id result
   */
  public function findById(InspectionId $id): ?Inspection
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionRecord) {
      return null;
    }

    return InspectionMapper::toDomain($this->reinterpretRecordDateTimesFromStorage($record));
  }

  public function findPublishedById(InspectionId $id): ?Inspection
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionRecord || 'published' !== $record->recordStatus) {
      return null;
    }

    return InspectionMapper::toDomain($this->reinterpretRecordDateTimesFromStorage($record));
  }

  /**
   * Method findByOrganizationId.
   *
   * Executes the find by organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   * @param ?string $equipmentId the equipment id value
   * @param ?string $facilityId the facility id value
   * @param ?string $result the result value
   * @param ?string $status the status value
   * @param ?string $performedAtFrom the performed at from value
   * @param ?string $performedAtTo the performed at to value
   * @param ?string $inspectorUserId the inspector user id value
   * @param ?string $inspectorType the inspector type value
   * @param ?string $checklistId the checklist id value
   * @param ?string $search the search value
   * @param Sorting $sorting the sorting value
   * @param int $limit the limit value
   * @param int $offset the offset value
   *
   * @return list<Inspection> the find by organization id result
   */
  public function findByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId = null,
    ?string $facilityId = null,
    ?string $result = null,
    ?string $status = null,
    ?string $performedAtFrom = null,
    ?string $performedAtTo = null,
    ?string $inspectorUserId = null,
    ?string $inspectorType = null,
    ?string $checklistId = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    /** @var list<InspectionRecord> $records */
    $records = $this->createListQueryBuilder(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom,
      $performedAtTo,
      $inspectorUserId,
      $inspectorType,
      $checklistId,
      $search,
    )
      ->orderBy($this->resolveSortField($sorting->field), strtoupper($sorting->direction->value))
      ->addOrderBy('i.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(
      fn (InspectionRecord $record): Inspection => InspectionMapper::toDomain($this->reinterpretRecordDateTimesFromStorage($record)),
      $records,
    );
  }

  /**
   * Method countByOrganizationId.
   *
   * Executes the count by organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   * @param ?string $equipmentId the equipment id value
   * @param ?string $facilityId the facility id value
   * @param ?string $result the result value
   * @param ?string $status the status value
   * @param ?string $performedAtFrom the performed at from value
   * @param ?string $performedAtTo the performed at to value
   * @param ?string $inspectorUserId the inspector user id value
   * @param ?string $inspectorType the inspector type value
   * @param ?string $checklistId the checklist id value
   * @param ?string $search the search value
   *
   * @return int the count by organization id result
   */
  public function countByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId = null,
    ?string $facilityId = null,
    ?string $result = null,
    ?string $status = null,
    ?string $performedAtFrom = null,
    ?string $performedAtTo = null,
    ?string $inspectorUserId = null,
    ?string $inspectorType = null,
    ?string $checklistId = null,
    ?string $search = null,
  ): int {
    return (int) $this->createListQueryBuilder(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom,
      $performedAtTo,
      $inspectorUserId,
      $inspectorType,
      $checklistId,
      $search,
    )
      ->select('COUNT(i.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Method findEquipmentIdsByIds.
   *
   * Single query resolving the equipment identifier of each requested
   * inspection, so a caller batching a page of non-conformities asks once
   * rather than once per row. Mirrors `ChecklistRepository::findNamesByIds()`.
   *
   * @since 1.0.0
   *
   * @param list<string> $inspectionIds
   *
   * @return array<string, string>
   */
  public function findEquipmentIdsByIds(array $inspectionIds): array
  {
    if ([] === $inspectionIds) {
      return [];
    }

    /** @var list<array{id: string, equipmentId: string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('i.id AS id', 'i.equipmentId AS equipmentId')
      ->from(InspectionRecord::class, 'i')
      ->where('i.id IN (:inspectionIds)')
      ->setParameter('inspectionIds', $inspectionIds)
      ->getQuery()
      ->getArrayResult();

    $equipmentIds = [];
    foreach ($rows as $row) {
      $equipmentIds[(string) $row['id']] = (string) $row['equipmentId'];
    }

    return $equipmentIds;
  }

  /**
   * Method countOverviewByOrganizationId.
   *
   * Executes the count overview by organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   * @param ?string $status the status value
   * @param ?string $result the result value
   * @param ?string $inspectorType the inspector type value
   *
   * @return array{total: int, draft: int, submitted: int, closed: int, pass: int, fail: int, partial: int} the count overview by organization id result
   */
  public function countOverviewByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select(
        'COUNT(i.id) AS total',
        'COALESCE(SUM(CASE WHEN i.status = :draftStatus THEN 1 ELSE 0 END), 0) AS draft',
        'COALESCE(SUM(CASE WHEN i.status = :submittedStatus THEN 1 ELSE 0 END), 0) AS submitted',
        'COALESCE(SUM(CASE WHEN i.status = :closedStatus THEN 1 ELSE 0 END), 0) AS closed',
        'COALESCE(SUM(CASE WHEN i.result = :passResult THEN 1 ELSE 0 END), 0) AS pass',
        'COALESCE(SUM(CASE WHEN i.result = :failResult THEN 1 ELSE 0 END), 0) AS fail',
        'COALESCE(SUM(CASE WHEN i.result = :partialResult THEN 1 ELSE 0 END), 0) AS partial',
      )
      ->from(InspectionRecord::class, 'i')
      ->where('i.organization = :organization')
      ->setParameter('organization', $organization)
      ->setParameter('draftStatus', 'draft')
      ->setParameter('submittedStatus', 'submitted')
      ->setParameter('closedStatus', 'closed')
      ->setParameter('passResult', 'pass')
      ->setParameter('failResult', 'fail')
      ->setParameter('partialResult', 'partial');

    if (null !== $status) {
      $queryBuilder->andWhere('i.status = :status')->setParameter('status', $status);
    }
    if (null !== $result) {
      $queryBuilder->andWhere('i.result = :result')->setParameter('result', $result);
    }
    if (null !== $inspectorType) {
      $queryBuilder->andWhere('i.inspectorType = :inspectorType')->setParameter('inspectorType', $inspectorType);
    }

    /** @var array{total?: int|string|null, draft?: int|string|null, submitted?: int|string|null, closed?: int|string|null, pass?: int|string|null, fail?: int|string|null, partial?: int|string|null} $row */
    $row = $queryBuilder->getQuery()->getSingleResult();

    return [
      'total' => (int) ($row['total'] ?? 0),
      'draft' => (int) ($row['draft'] ?? 0),
      'submitted' => (int) ($row['submitted'] ?? 0),
      'closed' => (int) ($row['closed'] ?? 0),
      'pass' => (int) ($row['pass'] ?? 0),
      'fail' => (int) ($row['fail'] ?? 0),
      'partial' => (int) ($row['partial'] ?? 0),
    ];
  }

  /**
   * Method countByStatusForOrganizationId.
   *
   * Executes the count by status for organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   *
   * @return array<string, int> map of status => count
   */
  public function countByStatusForOrganizationId(InspectionOrganizationId $organizationId): array
  {
    /** @var list<array{status: string, inspectionCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('i.status AS status, COUNT(i.id) AS inspectionCount')
      ->groupBy('i.status')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['status']] = (int) $row['inspectionCount'];
    }

    return $counts;
  }

  /**
   * Method countByResultForOrganizationId.
   *
   * Executes the count by result for organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   *
   * @return array<string, int> map of result => count
   */
  public function countByResultForOrganizationId(InspectionOrganizationId $organizationId): array
  {
    /** @var list<array{result: string, inspectionCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('i.result AS result, COUNT(i.id) AS inspectionCount')
      ->groupBy('i.result')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['result']] = (int) $row['inspectionCount'];
    }

    return $counts;
  }

  /**
   * Method countByInspectorTypeForOrganizationId.
   *
   * Executes the count by inspector type for organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   *
   * @return array<string, int> map of inspector type => count
   */
  public function countByInspectorTypeForOrganizationId(InspectionOrganizationId $organizationId): array
  {
    /** @var list<array{inspectorType: string, inspectionCount: int|string}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
      null,
    )
      ->select('i.inspectorType AS inspectorType, COUNT(i.id) AS inspectionCount')
      ->groupBy('i.inspectorType')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['inspectorType']] = (int) $row['inspectionCount'];
    }

    return $counts;
  }

  /**
   * Method countByPerformedDayForOrganizationId.
   *
   * Executes the count by performed day for organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   * @param string $performedAtFrom the performed at from value
   * @param string $performedAtTo the performed at to value
   * @param ?string $timeZone the time zone value
   * @param ?string $status the status value
   * @param ?string $result the result value
   * @param ?string $inspectorType the inspector type value
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByPerformedDayForOrganizationId(
    InspectionOrganizationId $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $timeZone = null,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    $bucketTimeZone = $this->resolveBucketTimeZone($timeZone, $performedAtFrom);
    if ('postgresql' === $this->entityManager->getConnection()->getDatabasePlatform()->getName()) {
      return $this->countByPerformedDayForOrganizationIdPostgreSql(
        organizationId: $organizationId,
        performedAtFrom: $performedAtFrom,
        performedAtTo: $performedAtTo,
        bucketTimeZone: $bucketTimeZone,
        status: $status,
        result: $result,
        inspectorType: $inspectorType,
      );
    }

    /** @var list<array{performedAt: DateTimeImmutable}> $rows */
    $rows = $this->createListQueryBuilder(
      $organizationId,
      null,
      null,
      $result,
      $status,
      $performedAtFrom,
      $performedAtTo,
      null,
      $inspectorType,
      null,
      null,
    )
      ->select('i.performedAt AS performedAt')
      ->orderBy('i.performedAt', 'ASC')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $bucket = $this->reinterpretStorageDateTime($row['performedAt'])->setTimezone($bucketTimeZone)->format('Y-m-d');
      $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }

    return $counts;
  }

  /**
   * Method countPeriodMetricsByOrganizationId.
   *
   * Executes the count period metrics by organization id operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   * @param string $performedAtFrom the performed at from value
   * @param string $performedAtTo the performed at to value
   * @param ?string $status the status value
   * @param ?string $result the result value
   * @param ?string $inspectorType the inspector type value
   *
   * @return array{total: int, closed: int, pass: int, fail: int, partial: int} the count period metrics by organization id result
   */
  public function countPeriodMetricsByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select(
        'COUNT(i.id) AS total',
        'COALESCE(SUM(CASE WHEN i.status = :closedStatus THEN 1 ELSE 0 END), 0) AS closed',
        'COALESCE(SUM(CASE WHEN i.result = :passResult THEN 1 ELSE 0 END), 0) AS pass',
        'COALESCE(SUM(CASE WHEN i.result = :failResult THEN 1 ELSE 0 END), 0) AS fail',
        'COALESCE(SUM(CASE WHEN i.result = :partialResult THEN 1 ELSE 0 END), 0) AS partial',
      )
      ->from(InspectionRecord::class, 'i')
      ->where('i.organization = :organization')
      ->andWhere('i.performedAt >= :performedAtFrom')
      ->andWhere('i.performedAt <= :performedAtTo')
      ->setParameter('organization', $organization)
      ->setParameter('performedAtFrom', $this->normalizeTimestampToStorageDateTime($performedAtFrom), Types::DATETIME_IMMUTABLE)
      ->setParameter('performedAtTo', $this->normalizeTimestampToStorageDateTime($performedAtTo), Types::DATETIME_IMMUTABLE)
      ->setParameter('closedStatus', 'closed')
      ->setParameter('passResult', 'pass')
      ->setParameter('failResult', 'fail')
      ->setParameter('partialResult', 'partial');

    if (null !== $status) {
      $queryBuilder->andWhere('i.status = :status')->setParameter('status', $status);
    }
    if (null !== $result) {
      $queryBuilder->andWhere('i.result = :result')->setParameter('result', $result);
    }
    if (null !== $inspectorType) {
      $queryBuilder->andWhere('i.inspectorType = :inspectorType')->setParameter('inspectorType', $inspectorType);
    }

    /** @var array{total?: int|string|null, closed?: int|string|null, pass?: int|string|null, fail?: int|string|null, partial?: int|string|null} $row */
    $row = $queryBuilder->getQuery()->getSingleResult();

    return [
      'total' => (int) ($row['total'] ?? 0),
      'closed' => (int) ($row['closed'] ?? 0),
      'pass' => (int) ($row['pass'] ?? 0),
      'fail' => (int) ($row['fail'] ?? 0),
      'partial' => (int) ($row['partial'] ?? 0),
    ];
  }

  /**
   * Method createListQueryBuilder.
   *
   * Executes the create list query builder operation.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the organization id value
   * @param ?string $equipmentId the equipment id value
   * @param ?string $facilityId the facility id value
   * @param ?string $result the result value
   * @param ?string $status the status value
   * @param ?string $performedAtFrom the performed at from value
   * @param ?string $performedAtTo the performed at to value
   * @param ?string $inspectorUserId the inspector user id value
   * @param ?string $inspectorType the inspector type value
   * @param ?string $checklistId the checklist id value
   * @param ?string $search the search value
   *
   * @return QueryBuilder the create list query builder result
   */
  private function createListQueryBuilder(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId,
    ?string $facilityId,
    ?string $result,
    ?string $status,
    ?string $performedAtFrom,
    ?string $performedAtTo,
    ?string $inspectorUserId,
    ?string $inspectorType,
    ?string $checklistId,
    ?string $search,
  ): QueryBuilder {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('i')
      ->from(InspectionRecord::class, 'i')
      ->where('i.organization = :organization')
      ->andWhere('i.recordStatus = :publishedRecordStatus')
      ->setParameter('publishedRecordStatus', 'published')
      ->setParameter('organization', $organization);

    if (null !== $equipmentId) {
      $queryBuilder
        ->andWhere('i.equipmentId = :equipmentId')
        ->setParameter('equipmentId', $equipmentId);
    }

    if (null !== $facilityId) {
      $queryBuilder
        ->andWhere('i.facilityId = :facilityId')
        ->setParameter('facilityId', $facilityId);
    }

    if (null !== $result) {
      $queryBuilder
        ->andWhere('i.result = :result')
        ->setParameter('result', $result);
    }

    if (null !== $status) {
      $queryBuilder
        ->andWhere('i.status = :status')
        ->setParameter('status', $status);
    }

    if (null !== $performedAtFrom) {
      $queryBuilder
        ->andWhere('i.performedAt >= :performedAtFrom')
        ->setParameter('performedAtFrom', $this->normalizeTimestampToStorageDateTime($performedAtFrom), Types::DATETIME_IMMUTABLE);
    }

    if (null !== $performedAtTo) {
      $queryBuilder
        ->andWhere('i.performedAt <= :performedAtTo')
        ->setParameter('performedAtTo', $this->normalizeTimestampToStorageDateTime($performedAtTo), Types::DATETIME_IMMUTABLE);
    }

    if (null !== $inspectorUserId) {
      $queryBuilder
        ->andWhere('i.inspectorUserId = :inspectorUserId')
        ->setParameter('inspectorUserId', $inspectorUserId);
    }

    if (null !== $inspectorType) {
      $queryBuilder
        ->andWhere('i.inspectorType = :inspectorType')
        ->setParameter('inspectorType', $inspectorType);
    }

    if (null !== $checklistId) {
      $queryBuilder
        ->andWhere('i.checklistId = :checklistId')
        ->setParameter('checklistId', $checklistId);
    }

    TrigramSearchExpression::apply(
      $queryBuilder,
      'search',
      $search,
      'i.result',
      'i.status',
      'i.inspectorName',
      'i.equipmentId',
      'i.facilityId',
      'i.checklistId',
    );

    return $queryBuilder;
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
      'result' => 'i.result',
      'status' => 'i.status',
      'performedAt' => 'i.performedAt',
      default => 'i.createdAt',
    };
  }

  /**
   * Method normalizeRecordDateTimesToStorage.
   *
   * Executes the normalize record date times to storage operation.
   *
   * @since 1.0.0
   *
   * @param InspectionRecord $record the record value
   *
   * @return InspectionRecord the normalize record date times to storage result
   */
  private function normalizeRecordDateTimesToStorage(InspectionRecord $record): InspectionRecord
  {
    $record->performedAt = $this->normalizeDateTimeForStorage($record->performedAt);
    $record->createdAt = $this->normalizeDateTimeForStorage($record->createdAt);
    $record->updatedAt = $this->normalizeDateTimeForStorage($record->updatedAt);

    return $record;
  }

  /**
   * Method reinterpretRecordDateTimesFromStorage.
   *
   * Executes the reinterpret record date times from storage operation.
   *
   * @since 1.0.0
   *
   * @param InspectionRecord $record the record value
   *
   * @return InspectionRecord the reinterpret record date times from storage result
   */
  private function reinterpretRecordDateTimesFromStorage(InspectionRecord $record): InspectionRecord
  {
    $normalized = clone $record;
    $normalized->performedAt = $this->reinterpretStorageDateTime($record->performedAt);
    $normalized->createdAt = $this->reinterpretStorageDateTime($record->createdAt);
    $normalized->updatedAt = $this->reinterpretStorageDateTime($record->updatedAt);

    return $normalized;
  }

  /**
   * @return array<string, int>
   */
  private function countByPerformedDayForOrganizationIdPostgreSql(
    InspectionOrganizationId $organizationId,
    string $performedAtFrom,
    string $performedAtTo,
    DateTimeZone $bucketTimeZone,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    $storageTimeZone = $this->resolveStorageTimeZone();
    $sql = <<<'SQL'
        SELECT
          TO_CHAR(((performed_at AT TIME ZONE :storageTimeZone) AT TIME ZONE :bucketTimeZone), 'YYYY-MM-DD') AS bucket,
          COUNT(*) AS inspection_count
        FROM inspections
        WHERE organization_id = :organizationId
          AND performed_at >= :performedAtFrom
          AND performed_at <= :performedAtTo
      SQL;
    $parameters = [
      'storageTimeZone' => $storageTimeZone->getName(),
      'bucketTimeZone' => $bucketTimeZone->getName(),
      'organizationId' => (string) $organizationId,
      'performedAtFrom' => $this->normalizeTimestampForStorageTimeZone($performedAtFrom, $storageTimeZone),
      'performedAtTo' => $this->normalizeTimestampForStorageTimeZone($performedAtTo, $storageTimeZone),
    ];

    if (null !== $status) {
      $sql .= "\n  AND status = :status";
      $parameters['status'] = $status;
    }

    if (null !== $result) {
      $sql .= "\n  AND result = :result";
      $parameters['result'] = $result;
    }

    if (null !== $inspectorType) {
      $sql .= "\n  AND inspector_type = :inspectorType";
      $parameters['inspectorType'] = $inspectorType;
    }

    $sql .= "\nGROUP BY 1\nORDER BY 1 ASC";

    /** @var list<array{bucket: string, inspection_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $parameters)->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['bucket']] = (int) $row['inspection_count'];
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
   * Method normalizeDateTimeForStorage.
   *
   * Executes the normalize date time for storage operation.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $value the value value
   *
   * @return DateTimeImmutable the normalize date time for storage result
   */
  private function normalizeDateTimeForStorage(DateTimeImmutable $value): DateTimeImmutable
  {
    return $value->setTimezone($this->resolveStorageTimeZone());
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
    $normalized = DateTimeImmutable::createFromFormat(
      '!Y-m-d H:i:s.u',
      $value->format('Y-m-d H:i:s.u'),
      $this->resolveStorageTimeZone(),
    );

    if (false === $normalized) {
      throw new RuntimeException('Unable to reinterpret a stored inspection datetime.');
    }

    return $normalized;
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
}
