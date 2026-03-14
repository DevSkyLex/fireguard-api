<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{NonConformityId, NonConformityInspectionId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\NonConformityMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};

use function array_map;

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
  ): array {
    /** @var InspectionRecord $inspection */
    $inspection = $this->entityManager->getReference(InspectionRecord::class, (string) $inspectionId);
    $criteria = ['inspection' => $inspection];

    if (null !== $severity) {
      $criteria['severity'] = $severity;
    }
    if (null !== $status) {
      $criteria['status'] = $status;
    }

    $records = $this->repository->findBy($criteria, ['createdAt' => 'DESC']);

    return array_map(
      static fn (NonConformityRecord $record): NonConformity => NonConformityMapper::toDomain($record),
      $records,
    );
  }

  public function countByInspectionId(NonConformityInspectionId $inspectionId): int
  {
    /** @var InspectionRecord $inspection */
    $inspection = $this->entityManager->getReference(InspectionRecord::class, (string) $inspectionId);

    return $this->repository->count(['inspection' => $inspection]);
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
}
