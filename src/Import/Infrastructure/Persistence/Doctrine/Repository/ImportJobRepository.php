<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Import\Application\Port\Outbound\ImportJobRepositoryPort;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};
use Import\Infrastructure\Persistence\Doctrine\Mapper\ImportJobMapper;
use Import\Infrastructure\Persistence\Doctrine\Record\ImportJobRecord;

use function array_map;

/**
 * Repository ImportJobRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ImportJobRepository implements ImportJobRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<ImportJobRecord>
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
  ) {
    $this->repository = $this->entityManager->getRepository(ImportJobRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(ImportJob $job): void
  {
    $record = $this->repository->find((string) $job->id());
    if (!$record instanceof ImportJobRecord) {
      $record = new ImportJobRecord();
      $this->entityManager->persist($record);
    }

    ImportJobMapper::toRecord($job, $record);

    $this->entityManager->flush();
  }

  public function findById(ImportJobId $id): ?ImportJob
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof ImportJobRecord ? ImportJobMapper::toDomain($record) : null;
  }

  public function listByOrganization(string $organizationId, ?ImportKind $kind, int $limit, int $offset): array
  {
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('j')
      ->from(ImportJobRecord::class, 'j')
      ->where('j.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId)
      ->orderBy('j.createdAt', 'DESC')
      ->addOrderBy('j.id', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    if (null !== $kind) {
      $queryBuilder->andWhere('j.kind = :kind')->setParameter('kind', $kind->value);
    }

    /** @var list<ImportJobRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    return array_map(ImportJobMapper::toDomain(...), $records);
  }

  public function countByOrganization(string $organizationId, ?ImportKind $kind): int
  {
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('COUNT(j.id)')
      ->from(ImportJobRecord::class, 'j')
      ->where('j.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId);

    if (null !== $kind) {
      $queryBuilder->andWhere('j.kind = :kind')->setParameter('kind', $kind->value);
    }

    return (int) $queryBuilder->getQuery()->getSingleScalarResult();
  }

  public function claim(ImportJobId $id): bool
  {
    // A raw DBAL statement — not the ORM's find()/flush() — mirrors
    // `AutomationRunRepository::reserveRun()`: claiming is a conditional
    // UPDATE guarded by a WHERE clause, not an exceptional path, and running
    // it outside the UnitOfWork keeps a concurrent/redelivered claim attempt
    // from ever touching the EntityManager's identity map.
    $now = new DateTimeImmutable();

    $affected = $this->entityManager->getConnection()->executeStatement(
      'UPDATE import_jobs '
      . 'SET status = :processing, started_at = COALESCE(started_at, :now), updated_at = :now '
      . "WHERE id = :id AND status IN ('pending', 'processing')",
      [
        'processing' => 'processing',
        'now' => $now,
        'id' => (string) $id,
      ],
      ['now' => 'datetime_immutable'],
    );

    return $affected > 0;
  }
  // #endregion
}
