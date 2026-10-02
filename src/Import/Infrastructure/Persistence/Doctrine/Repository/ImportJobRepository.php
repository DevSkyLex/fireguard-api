<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Import\Application\Port\Outbound\ImportJobRepositoryPort;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError};
use Import\Infrastructure\Persistence\Doctrine\Mapper\ImportJobMapper;
use Import\Infrastructure\Persistence\Doctrine\Record\ImportJobRecord;

use function array_map;
use function intdiv;
use function max;
use function min;

use const PHP_INT_MAX;

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
    $this->entityManager->getConnection()->transactional(function () use ($job): void {
      $record = $this->repository->find((string) $job->id());
      $newRecord = false;

      if (!$record instanceof ImportJobRecord) {
        $record = new ImportJobRecord();
        $newRecord = true;
      }

      ImportJobMapper::toRecord($job, $record, includeReport: false);

      if ($newRecord) {
        $this->entityManager->persist($record);
      }

      $this->entityManager->flush();
      foreach ($job->errorReport() as $report) {
        $this->entityManager->getConnection()->executeStatement(
          'INSERT INTO import_row_reports (import_job_id, row_number, code, message, column_name)
        VALUES (:job, :row, :code, :message, :column) ON CONFLICT DO NOTHING',
          ['job' => (string) $job->id(), 'row' => $report->rowNumber, 'code' => $report->code, 'message' => $report->message, 'column' => $report->column],
        );
      }
    });
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

    if (!$record instanceof ImportJobRecord) {
      return null;
    }

    return ImportJobMapper::toDomain($record, $this->reportPage($id, 1, 100));
  }

  /**
   * Loads worker counters while leaving the accumulated report detached.
   */
  public function findForExecution(ImportJobId $id): ?ImportJob
  {
    $record = $this->repository->find((string) $id);
    if (!$record instanceof ImportJobRecord) {
      return null;
    }
    $this->entityManager->refresh($record);

    return ImportJobMapper::toDomain($record, []);
  }

  /**
   * @return list<ImportRowError> report rows in stable file order
   */
  public function reportPage(ImportJobId $id, int $page, int $itemsPerPage): array
  {
    $limit = max(1, min(100, $itemsPerPage));
    $offset = (min(max(1, $page), intdiv(PHP_INT_MAX, $limit)) - 1) * $limit;
    /** @var list<array{row_number: int|string, code: string, message: string, column_name: ?string}> $rows */
    $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT row_number, code, message, column_name FROM import_row_reports WHERE import_job_id = :id ORDER BY row_number ASC LIMIT ' . $limit . ' OFFSET ' . $offset, ['id' => (string) $id]);

    return array_map(static fn (array $row): ImportRowError => new ImportRowError((int) $row['row_number'], $row['code'], $row['message'], $row['column_name']), $rows);
  }

  /**
   * Counts the confirmed row report without loading its contents.
   */
  public function countReport(ImportJobId $id): int
  {
    /** @var int|string $count */
    $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM import_row_reports WHERE import_job_id = :id', ['id' => (string) $id]);

    return (int) $count;
  }

  /**
   * @return list<int> simulation successes needed for one resume projection
   */
  public function confirmedSimulationRows(ImportJobId $id): array
  {
    /** @var list<int|string> $rows */
    $rows = $this->entityManager->getConnection()->fetchFirstColumn("SELECT row_number FROM import_row_reports WHERE import_job_id = :id AND code = 'would_create' ORDER BY row_number ASC", ['id' => (string) $id]);

    return array_map(static fn (int|string $row): int => (int) $row, $rows);
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

    return array_map(static fn (ImportJobRecord $record): ImportJob => ImportJobMapper::toDomain($record, []), $records);
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
