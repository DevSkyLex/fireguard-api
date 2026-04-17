<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Exception;
use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformityId, NonConformityInspectionId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\NonConformityMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use RuntimeException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_map;
use function str_replace;

final readonly class NonConformityRepository implements NonConformityRepositoryPort
{
  /**
   * @var EntityRepository<NonConformityRecord>
   */
  private EntityRepository $repository;

  public function __construct(
    private EntityManagerInterface $entityManager,
    #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')]
    private string $storageTimeZone = 'UTC',
  ) {
    $this->repository = $this->entityManager->getRepository(NonConformityRecord::class);
  }

  public function save(NonConformity $nonConformity): void
  {
    $record = $this->normalizeRecordDateTimesToStorage(NonConformityMapper::toRecord($nonConformity));
    /** @var InspectionRecord $inspection */
    $inspection = $this->entityManager->getReference(InspectionRecord::class, (string) $nonConformity->inspectionId());
    $record->inspection = $inspection;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof NonConformityRecord) {
      $existing->inspection = $inspection;
      $existing->description = $record->description;
      $existing->severity = $record->severity;
      $existing->status = $record->status;
      $existing->dueAt = $record->dueAt;
      $existing->resolvedAt = $record->resolvedAt;
      $existing->notes = $record->notes;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function findById(NonConformityId $id): ?NonConformity
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof NonConformityRecord) {
      return null;
    }

    return NonConformityMapper::toDomain($this->reinterpretRecordDateTimesFromStorage($record));
  }

  public function findByInspectionId(
    NonConformityInspectionId $inspectionId,
    ?string $severity = null,
    ?string $status = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    $qb = $this->createListQueryBuilder($inspectionId, $severity, $status, $search);
    $qb->orderBy('r.' . $this->resolveSortField($sorting->field), $sorting->direction->value)
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<NonConformityRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      fn (NonConformityRecord $record): NonConformity => NonConformityMapper::toDomain($this->reinterpretRecordDateTimesFromStorage($record)),
      $records,
    );
  }

  public function countByInspectionId(
    NonConformityInspectionId $inspectionId,
    ?string $severity = null,
    ?string $status = null,
    ?string $search = null,
  ): int {
    $qb = $this->createListQueryBuilder($inspectionId, $severity, $status, $search);
    $qb->select('COUNT(r.id)');

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  /**
   * Method countsByInspectionIds.
   *
   * @since 1.0.0
   *
   * @param list<string> $inspectionIds
   *
   * @return array<string, int>
   */
  public function countsByInspectionIds(array $inspectionIds): array
  {
    if ([] === $inspectionIds) {
      return [];
    }

    $qb = $this->entityManager->createQueryBuilder();

    /** @var list<array{inspectionId: string, cnt: int|string}> $rows */
    $rows = $qb
      ->select('IDENTITY(r.inspection) AS inspectionId, COUNT(r.id) AS cnt')
      ->from(NonConformityRecord::class, 'r')
      ->where($qb->expr()->in('IDENTITY(r.inspection)', ':ids'))
      ->setParameter('ids', $inspectionIds)
      ->groupBy('r.inspection')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['inspectionId']] = (int) $row['cnt'];
    }

    return $counts;
  }

  public function countByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $severity = null,
    ?string $status = null,
    ?string $search = null,
  ): int {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('COUNT(r.id)')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->setParameter('organizationId', (string) $organizationId);

    if (null !== $severity) {
      $qb->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }

    if (null !== $status) {
      $qb->andWhere('r.status = :status')->setParameter('status', $status);
    }

    if (null !== $search && '' !== $search) {
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
      $qb->andWhere($qb->expr()->orX(
        $qb->expr()->like('r.description', ':search'),
        $qb->expr()->like('r.severity', ':search'),
        $qb->expr()->like('r.status', ':search'),
        $qb->expr()->like('r.notes', ':search'),
      ))->setParameter('search', '%' . $escaped . '%');
    }

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  public function countOverviewByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select(
        'COUNT(r.id) AS total',
        'COALESCE(SUM(CASE WHEN r.status = :openStatus THEN 1 ELSE 0 END), 0) AS openCount',
        'COALESCE(SUM(CASE WHEN r.status = :inProgressStatus THEN 1 ELSE 0 END), 0) AS inProgressCount',
        'COALESCE(SUM(CASE WHEN r.status = :doneStatus THEN 1 ELSE 0 END), 0) AS doneCount',
        'COALESCE(SUM(CASE WHEN r.status = :waivedStatus THEN 1 ELSE 0 END), 0) AS waivedCount',
        'COALESCE(SUM(CASE WHEN r.dueAt IS NOT NULL AND r.dueAt < :dueAtBefore AND r.status IN (:openStatuses) THEN 1 ELSE 0 END), 0) AS overdueCount',
        'COALESCE(SUM(CASE WHEN r.severity = :criticalSeverity AND r.status IN (:openStatuses) THEN 1 ELSE 0 END), 0) AS criticalOpenCount',
      )
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->where('o.id = :organizationId')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('openStatus', 'open')
      ->setParameter('inProgressStatus', 'in_progress')
      ->setParameter('doneStatus', 'done')
      ->setParameter('waivedStatus', 'waived')
      ->setParameter('openStatuses', ['open', 'in_progress'])
      ->setParameter('criticalSeverity', 'critical')
      ->setParameter('dueAtBefore', $this->normalizeTimestampToStorageDateTime($dueAtBefore), Types::DATETIME_IMMUTABLE);

    if (null !== $severity) {
      $queryBuilder->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }
    if (null !== $status) {
      $queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);
    }

    /** @var array{total?: int|string|null, openCount?: int|string|null, inProgressCount?: int|string|null, doneCount?: int|string|null, waivedCount?: int|string|null, overdueCount?: int|string|null, criticalOpenCount?: int|string|null} $row */
    $row = $queryBuilder->getQuery()->getSingleResult();

    return [
      'total' => (int) ($row['total'] ?? 0),
      'open' => (int) ($row['openCount'] ?? 0),
      'in_progress' => (int) ($row['inProgressCount'] ?? 0),
      'done' => (int) ($row['doneCount'] ?? 0),
      'waived' => (int) ($row['waivedCount'] ?? 0),
      'overdue' => (int) ($row['overdueCount'] ?? 0),
      'critical_open' => (int) ($row['criticalOpenCount'] ?? 0),
    ];
  }

  public function countByStatusForOrganizationId(InspectionOrganizationId $organizationId): array
  {
    /** @var list<array{status: string, nonConformityCount: int|string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('r.status AS status, COUNT(r.id) AS nonConformityCount')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->setParameter('organizationId', (string) $organizationId)
      ->groupBy('r.status')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['status']] = (int) $row['nonConformityCount'];
    }

    return $counts;
  }

  public function countBySeverityForOrganizationId(InspectionOrganizationId $organizationId): array
  {
    /** @var list<array{severity: string, nonConformityCount: int|string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('r.severity AS severity, COUNT(r.id) AS nonConformityCount')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->setParameter('organizationId', (string) $organizationId)
      ->groupBy('r.severity')
      ->getQuery()
      ->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['severity']] = (int) $row['nonConformityCount'];
    }

    return $counts;
  }

  public function countOverdueByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $dueAtBefore,
    ?string $severity = null,
    ?string $status = null,
  ): int {
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('COUNT(r.id)')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->andWhere('r.dueAt IS NOT NULL')
      ->andWhere('r.dueAt < :dueAtBefore')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('dueAtBefore', $this->normalizeTimestampToStorageDateTime($dueAtBefore), Types::DATETIME_IMMUTABLE);

    if (null !== $severity) {
      $queryBuilder->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }

    if (null !== $status) {
      $queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);
    } else {
      $queryBuilder->andWhere('r.status IN (:openStatuses)')->setParameter('openStatuses', ['open', 'in_progress']);
    }

    return (int) $queryBuilder
      ->getQuery()
      ->getSingleScalarResult();
  }

  public function countActiveByOrganizationIdAtDate(
    InspectionOrganizationId $organizationId,
    string $at,
    ?string $severity = null,
    ?string $status = null,
  ): int {
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('COUNT(r.id)')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->andWhere('r.createdAt < :at')
      ->andWhere('(r.resolvedAt IS NULL OR r.resolvedAt > :at)')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('at', $this->normalizeTimestampToStorageDateTime($at), Types::DATETIME_IMMUTABLE);

    if (null !== $severity) {
      $queryBuilder->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }

    if (null !== $status) {
      $queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);
    }

    return (int) $queryBuilder
      ->getQuery()
      ->getSingleScalarResult();
  }

  public function countByCreatedDayForOrganizationId(
    InspectionOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    $bucketTimeZone = $this->resolveBucketTimeZone($timeZone, $createdAtFrom);
    if ('postgresql' === $this->entityManager->getConnection()->getDatabasePlatform()->getName()) {
      return $this->countByCreatedDayForOrganizationIdPostgreSql(
        organizationId: $organizationId,
        createdAtFrom: $createdAtFrom,
        createdAtTo: $createdAtTo,
        bucketTimeZone: $bucketTimeZone,
        severity: $severity,
        status: $status,
      );
    }

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('r.createdAt AS createdAt')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->andWhere('r.createdAt >= :createdAtFrom')
      ->andWhere('r.createdAt <= :createdAtTo')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('createdAtFrom', $this->normalizeTimestampToStorageDateTime($createdAtFrom), Types::DATETIME_IMMUTABLE)
      ->setParameter('createdAtTo', $this->normalizeTimestampToStorageDateTime($createdAtTo), Types::DATETIME_IMMUTABLE)
      ->orderBy('r.createdAt', 'ASC');

    if (null !== $severity) {
      $queryBuilder->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }

    if (null !== $status) {
      $queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);
    }

    /** @var list<array{createdAt: DateTimeImmutable}> $rows */
    $rows = $queryBuilder->getQuery()->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $bucket = $this->reinterpretStorageDateTime($row['createdAt'])->setTimezone($bucketTimeZone)->format('Y-m-d');
      $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }

    return $counts;
  }

  public function countByResolvedDayForOrganizationId(
    InspectionOrganizationId $organizationId,
    string $resolvedAtFrom,
    string $resolvedAtTo,
    ?string $timeZone = null,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    $bucketTimeZone = $this->resolveBucketTimeZone($timeZone, $resolvedAtFrom);
    if ('postgresql' === $this->entityManager->getConnection()->getDatabasePlatform()->getName()) {
      return $this->countByResolvedDayForOrganizationIdPostgreSql(
        organizationId: $organizationId,
        resolvedAtFrom: $resolvedAtFrom,
        resolvedAtTo: $resolvedAtTo,
        bucketTimeZone: $bucketTimeZone,
        severity: $severity,
        status: $status,
      );
    }

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('r.resolvedAt AS resolvedAt')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->andWhere('r.resolvedAt IS NOT NULL')
      ->andWhere('r.resolvedAt >= :resolvedAtFrom')
      ->andWhere('r.resolvedAt <= :resolvedAtTo')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('resolvedAtFrom', $this->normalizeTimestampToStorageDateTime($resolvedAtFrom), Types::DATETIME_IMMUTABLE)
      ->setParameter('resolvedAtTo', $this->normalizeTimestampToStorageDateTime($resolvedAtTo), Types::DATETIME_IMMUTABLE)
      ->orderBy('r.resolvedAt', 'ASC');

    if (null !== $severity) {
      $queryBuilder->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }

    if (null !== $status) {
      $queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);
    }

    /** @var list<array{resolvedAt: DateTimeImmutable}> $rows */
    $rows = $queryBuilder->getQuery()->getArrayResult();

    $counts = [];
    foreach ($rows as $row) {
      $bucket = $this->reinterpretStorageDateTime($row['resolvedAt'])->setTimezone($bucketTimeZone)->format('Y-m-d');
      $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }

    return $counts;
  }

  public function countPeriodMetricsByOrganizationId(
    InspectionOrganizationId $organizationId,
    string $periodFrom,
    string $periodTo,
    string $activeAt,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    $periodFromDate = $this->normalizeTimestampToStorageDateTime($periodFrom);
    $periodToDate = $this->normalizeTimestampToStorageDateTime($periodTo);
    $activeAtDate = $this->normalizeTimestampToStorageDateTime($activeAt);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select(
        'COALESCE(SUM(CASE WHEN r.createdAt >= :periodFrom AND r.createdAt <= :periodTo THEN 1 ELSE 0 END), 0) AS openedCount',
        'COALESCE(SUM(CASE WHEN r.resolvedAt IS NOT NULL AND r.resolvedAt >= :periodFrom AND r.resolvedAt <= :periodTo THEN 1 ELSE 0 END), 0) AS resolvedCount',
        'COALESCE(SUM(CASE WHEN r.createdAt < :activeAt AND (r.resolvedAt IS NULL OR r.resolvedAt > :activeAt) THEN 1 ELSE 0 END), 0) AS activeAtStartCount',
      )
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->where('o.id = :organizationId')
      ->andWhere('(
        (r.createdAt >= :periodFrom AND r.createdAt <= :periodTo) OR
        (r.resolvedAt IS NOT NULL AND r.resolvedAt >= :periodFrom AND r.resolvedAt <= :periodTo) OR
        (r.createdAt < :activeAt AND (r.resolvedAt IS NULL OR r.resolvedAt > :activeAt))
      )')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('periodFrom', $periodFromDate, Types::DATETIME_IMMUTABLE)
      ->setParameter('periodTo', $periodToDate, Types::DATETIME_IMMUTABLE)
      ->setParameter('activeAt', $activeAtDate, Types::DATETIME_IMMUTABLE);

    if (null !== $severity) {
      $queryBuilder->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }
    if (null !== $status) {
      $queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);
    }

    /** @var array{openedCount?: int|string|null, resolvedCount?: int|string|null, activeAtStartCount?: int|string|null} $row */
    $row = $queryBuilder->getQuery()->getSingleResult();

    return [
      'opened' => (int) ($row['openedCount'] ?? 0),
      'resolved' => (int) ($row['resolvedCount'] ?? 0),
      'activeAtStart' => (int) ($row['activeAtStartCount'] ?? 0),
    ];
  }

  public function countOpenCriticalByOrganizationId(InspectionOrganizationId $organizationId, ?string $status = null): int
  {
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('COUNT(r.id)')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->andWhere('r.severity = :severity')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('severity', 'critical');

    if (null !== $status) {
      $queryBuilder->andWhere('r.status = :status')->setParameter('status', $status);
    } else {
      $queryBuilder->andWhere('r.status IN (:openStatuses)')->setParameter('openStatuses', ['open', 'in_progress']);
    }

    return (int) $queryBuilder
      ->getQuery()
      ->getSingleScalarResult();
  }

  private function createListQueryBuilder(
    NonConformityInspectionId $inspectionId,
    ?string $severity,
    ?string $status,
    ?string $search,
  ): QueryBuilder {
    /** @var InspectionRecord $inspection */
    $inspection = $this->entityManager->getReference(InspectionRecord::class, (string) $inspectionId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(NonConformityRecord::class, 'r')
      ->andWhere('r.inspection = :inspection')
      ->setParameter('inspection', $inspection);

    if (null !== $severity) {
      $qb->andWhere('r.severity = :severity')->setParameter('severity', $severity);
    }

    if (null !== $status) {
      $qb->andWhere('r.status = :status')->setParameter('status', $status);
    }

    if (null !== $search && '' !== $search) {
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
      $qb->andWhere($qb->expr()->orX(
        $qb->expr()->like('r.description', ':search'),
        $qb->expr()->like('r.severity', ':search'),
        $qb->expr()->like('r.status', ':search'),
        $qb->expr()->like('r.notes', ':search'),
      ))->setParameter('search', '%' . $escaped . '%');
    }

    return $qb;
  }

  private function resolveSortField(string $field): string
  {
    return match ($field) {
      'severity' => 'severity',
      'status' => 'status',
      'dueAt' => 'dueAt',
      default => 'createdAt',
    };
  }

  private function normalizeRecordDateTimesToStorage(NonConformityRecord $record): NonConformityRecord
  {
    $record->dueAt = $this->normalizeNullableDateTimeForStorage($record->dueAt);
    $record->resolvedAt = $this->normalizeNullableDateTimeForStorage($record->resolvedAt);
    $record->createdAt = $this->normalizeDateTimeForStorage($record->createdAt);
    $record->updatedAt = $this->normalizeDateTimeForStorage($record->updatedAt);

    return $record;
  }

  private function reinterpretRecordDateTimesFromStorage(NonConformityRecord $record): NonConformityRecord
  {
    $normalized = clone $record;
    $normalized->dueAt = $this->reinterpretNullableStorageDateTime($record->dueAt);
    $normalized->resolvedAt = $this->reinterpretNullableStorageDateTime($record->resolvedAt);
    $normalized->createdAt = $this->reinterpretStorageDateTime($record->createdAt);
    $normalized->updatedAt = $this->reinterpretStorageDateTime($record->updatedAt);

    return $normalized;
  }

  /**
   * @return array<string, int>
   */
  private function countByCreatedDayForOrganizationIdPostgreSql(
    InspectionOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    DateTimeZone $bucketTimeZone,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    $storageTimeZone = $this->resolveStorageTimeZone();
    $sql = <<<'SQL'
        SELECT
          TO_CHAR(((r.created_at AT TIME ZONE :storageTimeZone) AT TIME ZONE :bucketTimeZone), 'YYYY-MM-DD') AS bucket,
          COUNT(*) AS non_conformity_count
        FROM non_conformities r
        INNER JOIN inspections i ON i.id = r.inspection_id
        WHERE i.organization_id = :organizationId
          AND r.created_at >= :createdAtFrom
          AND r.created_at <= :createdAtTo
      SQL;
    $parameters = [
      'storageTimeZone' => $storageTimeZone->getName(),
      'bucketTimeZone' => $bucketTimeZone->getName(),
      'organizationId' => (string) $organizationId,
      'createdAtFrom' => $this->normalizeTimestampForStorageTimeZone($createdAtFrom, $storageTimeZone),
      'createdAtTo' => $this->normalizeTimestampForStorageTimeZone($createdAtTo, $storageTimeZone),
    ];

    if (null !== $severity) {
      $sql .= "\n  AND r.severity = :severity";
      $parameters['severity'] = $severity;
    }

    if (null !== $status) {
      $sql .= "\n  AND r.status = :status";
      $parameters['status'] = $status;
    }

    $sql .= "\nGROUP BY 1\nORDER BY 1 ASC";

    /** @var list<array{bucket: string, non_conformity_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $parameters)->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['bucket']] = (int) $row['non_conformity_count'];
    }

    return $counts;
  }

  /**
   * @return array<string, int>
   */
  private function countByResolvedDayForOrganizationIdPostgreSql(
    InspectionOrganizationId $organizationId,
    string $resolvedAtFrom,
    string $resolvedAtTo,
    DateTimeZone $bucketTimeZone,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    $storageTimeZone = $this->resolveStorageTimeZone();
    $sql = <<<'SQL'
        SELECT
          TO_CHAR(((r.resolved_at AT TIME ZONE :storageTimeZone) AT TIME ZONE :bucketTimeZone), 'YYYY-MM-DD') AS bucket,
          COUNT(*) AS non_conformity_count
        FROM non_conformities r
        INNER JOIN inspections i ON i.id = r.inspection_id
        WHERE i.organization_id = :organizationId
          AND r.resolved_at IS NOT NULL
          AND r.resolved_at >= :resolvedAtFrom
          AND r.resolved_at <= :resolvedAtTo
      SQL;
    $parameters = [
      'storageTimeZone' => $storageTimeZone->getName(),
      'bucketTimeZone' => $bucketTimeZone->getName(),
      'organizationId' => (string) $organizationId,
      'resolvedAtFrom' => $this->normalizeTimestampForStorageTimeZone($resolvedAtFrom, $storageTimeZone),
      'resolvedAtTo' => $this->normalizeTimestampForStorageTimeZone($resolvedAtTo, $storageTimeZone),
    ];

    if (null !== $severity) {
      $sql .= "\n  AND r.severity = :severity";
      $parameters['severity'] = $severity;
    }

    if (null !== $status) {
      $sql .= "\n  AND r.status = :status";
      $parameters['status'] = $status;
    }

    $sql .= "\nGROUP BY 1\nORDER BY 1 ASC";

    /** @var list<array{bucket: string, non_conformity_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, $parameters)->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $counts[(string) $row['bucket']] = (int) $row['non_conformity_count'];
    }

    return $counts;
  }

  private function resolveBucketTimeZone(?string $timeZone, string $lowerBound): DateTimeZone
  {
    if (null !== $timeZone && '' !== $timeZone) {
      return new DateTimeZone($timeZone);
    }

    return new DateTimeImmutable($lowerBound)->getTimezone();
  }

  private function resolveStorageTimeZone(): DateTimeZone
  {
    try {
      return new DateTimeZone($this->storageTimeZone);
    } catch (Exception $exception) {
      throw new RuntimeException('Invalid DATABASE_STORAGE_TIMEZONE configuration.', 0, $exception);
    }
  }

  private function normalizeTimestampToStorageDateTime(string $value): DateTimeImmutable
  {
    return new DateTimeImmutable($value)
      ->setTimezone($this->resolveStorageTimeZone());
  }

  private function normalizeDateTimeForStorage(DateTimeImmutable $value): DateTimeImmutable
  {
    return $value->setTimezone($this->resolveStorageTimeZone());
  }

  private function normalizeNullableDateTimeForStorage(?DateTimeImmutable $value): ?DateTimeImmutable
  {
    if (null === $value) {
      return null;
    }

    return $this->normalizeDateTimeForStorage($value);
  }

  private function reinterpretStorageDateTime(DateTimeImmutable $value): DateTimeImmutable
  {
    $normalized = DateTimeImmutable::createFromFormat(
      '!Y-m-d H:i:s.u',
      $value->format('Y-m-d H:i:s.u'),
      $this->resolveStorageTimeZone(),
    );

    if (false === $normalized) {
      throw new RuntimeException('Unable to reinterpret a stored non-conformity datetime.');
    }

    return $normalized;
  }

  private function reinterpretNullableStorageDateTime(?DateTimeImmutable $value): ?DateTimeImmutable
  {
    if (null === $value) {
      return null;
    }

    return $this->reinterpretStorageDateTime($value);
  }

  private function normalizeTimestampForStorageTimeZone(string $value, DateTimeZone $storageTimeZone): string
  {
    return new DateTimeImmutable($value)
      ->setTimezone($storageTimeZone)
      ->format('Y-m-d H:i:s.u');
  }
}
