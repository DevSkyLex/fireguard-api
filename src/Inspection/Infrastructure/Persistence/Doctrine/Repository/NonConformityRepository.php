<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformityId, NonConformityInspectionId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\NonConformityMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

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
  ) {
    $this->repository = $this->entityManager->getRepository(NonConformityRecord::class);
  }

  public function save(NonConformity $nonConformity): void
  {
    $record = NonConformityMapper::toRecord($nonConformity);
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

    return NonConformityMapper::toDomain($record);
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
      static fn (NonConformityRecord $record): NonConformity => NonConformityMapper::toDomain($record),
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
}
