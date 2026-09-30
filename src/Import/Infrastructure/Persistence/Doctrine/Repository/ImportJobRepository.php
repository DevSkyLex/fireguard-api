<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Repository;

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
  /**
   * Method save.
   *
   * Inserts or updates the persistence record for an import job.
   *
   * @access public
   *
   * @param ImportJob $job the import job aggregate
   *
   * @return void no return value
   */
  public function save(ImportJob $job): void
  {
    $record = $this->repository->find((string) $job->id());
    $newRecord = false;

    if (!$record instanceof ImportJobRecord) {
      $record = new ImportJobRecord();
      $newRecord = true;
    }

    ImportJobMapper::toRecord($job, $record);

    if ($newRecord) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Reloads an import job record and maps it to its domain aggregate.
   *
   * @access public
   *
   * @param ImportJobId $id the import job identifier
   *
   * @return ?ImportJob the aggregate when found
   */
  public function findById(ImportJobId $id): ?ImportJob
  {
    $record = $this->repository->find((string) $id);
    if ($record instanceof ImportJobRecord) {
      $this->entityManager->refresh($record);
    }

    return $record instanceof ImportJobRecord ? ImportJobMapper::toDomain($record) : null;
  }

  /**
   * Method listByOrganization.
   *
   * Lists organization import jobs filtered by kind and optional permitted kinds.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?ImportKind $kind optional kind filter
   * @param int $limit maximum number of jobs to return
   * @param int $offset number of earlier jobs to skip
   * @param ?list<ImportKind> $allowedKinds optional caller-permitted kinds
   *
   * @return list<ImportJob> matching import jobs, newest first
   */
  public function listByOrganization(string $organizationId, ?ImportKind $kind, int $limit, int $offset, ?array $allowedKinds = null): array
  {
    if ([] === $allowedKinds) {
      return [];
    }
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

    if (null !== $allowedKinds) {
      $queryBuilder->andWhere('j.kind IN (:allowedKinds)')
        ->setParameter('allowedKinds', array_map(static fn (ImportKind $allowed): string => $allowed->value, $allowedKinds));
    }

    /** @var list<ImportJobRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    return array_map(ImportJobMapper::toDomain(...), $records);
  }

  /**
   * Method countByOrganization.
   *
   * Counts organization import jobs using the same kind filters as the list query.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?ImportKind $kind optional kind filter
   * @param ?list<ImportKind> $allowedKinds optional caller-permitted kinds
   *
   * @return int number of matching import jobs
   */
  public function countByOrganization(string $organizationId, ?ImportKind $kind, ?array $allowedKinds = null): int
  {
    if ([] === $allowedKinds) {
      return 0;
    }
    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('COUNT(j.id)')
      ->from(ImportJobRecord::class, 'j')
      ->where('j.organizationId = :organizationId')
      ->setParameter('organizationId', $organizationId);

    if (null !== $kind) {
      $queryBuilder->andWhere('j.kind = :kind')->setParameter('kind', $kind->value);
    }

    if (null !== $allowedKinds) {
      $queryBuilder->andWhere('j.kind IN (:allowedKinds)')
        ->setParameter('allowedKinds', array_map(static fn (ImportKind $allowed): string => $allowed->value, $allowedKinds));
    }

    return (int) $queryBuilder->getQuery()->getSingleScalarResult();
  }

  // #endregion
}
