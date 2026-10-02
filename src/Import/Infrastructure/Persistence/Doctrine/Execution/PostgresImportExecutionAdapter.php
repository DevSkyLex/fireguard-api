<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Execution;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Import\Application\Exception\ImportLeaseUnavailable;
use Import\Application\Port\Outbound\{ImportExecutionPort, ImportJobRepositoryPort};
use Import\Domain\Exception\ImportJobNotFoundException;
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\ImportJobId;
use LogicException;
use Throwable;

use function array_key_last;

/**
 * Class PostgresImportExecutionAdapter
 *
 * Fences import worker operations with main-database leases, row locks, and per-row receipts.
 *
 * @category Adapter
 */
final readonly class PostgresImportExecutionAdapter implements ImportExecutionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the main connection, entity manager, manager registry, and import job repository.
   *
   * @access public
   *
   * @param Connection $connection main business database connection
   * @param EntityManagerInterface $entityManager main entity manager used by the repository
   * @param ManagerRegistry $registry manager registry used to reset a closed manager
   * @param ImportJobRepositoryPort $repository import job lookup and persistence
   *
   * @return void
   */
  public function __construct(
    private Connection $connection,
    private EntityManagerInterface $entityManager,
    private ManagerRegistry $registry,
    private ImportJobRepositoryPort $repository,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method claim
   *
   * Claims an expired or pending job lease and returns its latest persisted state.
   *
   * @access public
   *
   * @param ImportJobId $id import job identifier
   * @param string $owner worker lease owner token
   *
   * @return ImportJob|null claimed job, or null when no claim was made
   *
   * @throws ImportLeaseUnavailable when another worker still owns the lease
   */
  public function claim(ImportJobId $id, string $owner): ?ImportJob
  {
    $claimed = $this->connection->executeStatement(
      "UPDATE import_jobs SET status = 'processing', lease_owner = :owner,
        lease_expires_at = clock_timestamp() + INTERVAL '120 seconds',
        started_at = COALESCE(started_at, clock_timestamp()), updated_at = clock_timestamp()
       WHERE id = :id AND status IN ('pending', 'processing')
         AND (lease_expires_at IS NULL OR lease_expires_at <= clock_timestamp())",
      ['id' => (string) $id, 'owner' => $owner],
    );
    $job = $this->repository->findForExecution($id);
    if (0 === $claimed && null !== $job && !$job->status()->isTerminal()) {
      throw new ImportLeaseUnavailable();
    }

    return $claimed > 0 ? $job : null;
  }

  /**
   * Method run
   *
   * Runs one leased operation under a row lock, saves progress, records an optional row receipt, and renews the lease.
   *
   * @access public
   *
   * @param ImportJobId $id import job identifier
   * @param string $owner worker lease owner token
   * @param callable(ImportJob): ?string $operation operation returning an optional created resource identifier
   * @param int|null $rowNumber optional row number to confirm after the operation
   *
   * @return ImportJob updated job after the operation
   *
   * @throws ImportLeaseUnavailable when the job is not held by this unexpired lease
   * @throws LogicException when the locked job is missing or row order is invalid
   */
  public function run(ImportJobId $id, string $owner, callable $operation, ?int $rowNumber = null): ImportJob
  {
    try {
      return $this->connection->transactional(function () use ($id, $owner, $operation, $rowNumber): ImportJob {
        $locked = $this->connection->fetchOne(
          "SELECT id FROM import_jobs WHERE id = :id AND lease_owner = :owner
             AND lease_expires_at > clock_timestamp() AND status = 'processing' FOR UPDATE",
          ['id' => (string) $id, 'owner' => $owner],
        );
        if (false === $locked) {
          throw new ImportLeaseUnavailable();
        }
        $job = $this->repository->findForExecution($id) ?? throw new LogicException('The locked import no longer exists.');
        if (null !== $rowNumber && $rowNumber <= $job->processedRows()) {
          return $job;
        }
        if (null !== $rowNumber && $rowNumber !== $job->processedRows() + 1) {
          throw new LogicException('Import rows must be confirmed in file order.');
        }
        $failures = $job->failedRows();
        $resourceId = $operation($job);

        // A provisioning handler may translate a rejected nested ORM transaction
        // into a row outcome. Doctrine closes that manager on rollback; reset its
        // lazy service before saving progress, without replacing the main connection.
        $this->resetClosedManager();
        $this->repository->save($job);
        if (null !== $rowNumber) {
          $this->confirmRow($id, $job, $rowNumber, $failures, $resourceId);
        }
        $this->connection->executeStatement(
          "UPDATE import_jobs SET lease_expires_at = clock_timestamp() + INTERVAL '120 seconds'
           WHERE id = :id AND lease_owner = :owner",
          ['id' => (string) $id, 'owner' => $owner],
        );

        return $job;
      });
    } catch (Throwable $exception) {
      $this->resetClosedManager();
      $this->entityManager->clear();

      throw $exception;
    }
  }

  /**
   * Method release
   *
   * Clears the lease only when the supplied worker still owns it.
   *
   * @access public
   *
   * @param ImportJobId $id import job identifier
   * @param string $owner worker lease owner token
   *
   * @return void
   */
  public function release(ImportJobId $id, string $owner): void
  {
    $this->connection->executeStatement(
      'UPDATE import_jobs SET lease_owner = NULL, lease_expires_at = NULL WHERE id = :id AND lease_owner = :owner',
      ['id' => (string) $id, 'owner' => $owner],
    );
  }

  /**
   * Method canResume
   *
   * Checks whether a non-completed job has no active lease.
   *
   * @access public
   *
   * @param ImportJobId $id import job identifier
   *
   * @return bool whether the job can be resumed
   */
  public function canResume(ImportJobId $id): bool
  {
    return false !== $this->connection->fetchOne(
      "SELECT id FROM import_jobs WHERE id = :id AND status <> 'completed'
       AND (lease_expires_at IS NULL OR lease_expires_at <= clock_timestamp())",
      ['id' => (string) $id],
    );
  }

  /**
   * Method resume
   *
   * Locks and resumes a job without an active lease, persists it, clears lease fields, and enqueues it.
   *
   * @access public
   *
   * @param ImportJobId $id import job identifier
   * @param callable(ImportJob): void $enqueue callback that schedules the resumed job
   *
   * @return ImportJob resumed job
   *
   * @throws ImportJobNotFoundException when the job does not exist
   * @throws ImportLeaseUnavailable when the job still has an active lease
   */
  public function resume(ImportJobId $id, callable $enqueue): ImportJob
  {
    return $this->connection->transactional(function () use ($id, $enqueue): ImportJob {
      $locked = $this->connection->fetchOne('SELECT id FROM import_jobs WHERE id = :id FOR UPDATE', ['id' => (string) $id]);
      if (false === $locked) {
        throw ImportJobNotFoundException::withId((string) $id);
      }
      if (!$this->canResume($id)) {
        throw new ImportLeaseUnavailable();
      }
      $job = $this->repository->findForExecution($id) ?? throw ImportJobNotFoundException::withId((string) $id);
      $job->resume(new DateTimeImmutable());
      $this->repository->save($job);
      $this->connection->executeStatement('UPDATE import_jobs SET lease_owner = NULL, lease_expires_at = NULL WHERE id = :id', ['id' => (string) $id]);
      $enqueue($job);

      return $job;
    });
  }

  /**
   * Method confirmRow
   *
   * Records the durable outcome for a processed row after verifying its sequence and result.
   *
   * @access private
   *
   * @param ImportJobId $id import job identifier
   * @param ImportJob $job updated import job
   * @param int $rowNumber row number being confirmed
   * @param int $previousFailures failure count before the operation
   * @param string|null $resourceId created resource identifier, when available
   *
   * @return void
   *
   * @throws LogicException when the operation did not report exactly this row
   */
  private function confirmRow(ImportJobId $id, ImportJob $job, int $rowNumber, int $previousFailures, ?string $resourceId): void
  {
    if ($job->processedRows() !== $rowNumber) {
      throw new LogicException('The row operation must report exactly one outcome.');
    }
    $outcome = $job->isDryRun() ? 'would_create' : 'created';
    if ($job->failedRows() > $previousFailures) {
      $report = $job->errorReport();
      $last = array_key_last($report);
      $outcome = null !== $last ? $report[$last]->code : 'invalid';
    }
    $this->connection->executeStatement(
      'INSERT INTO import_row_receipts (import_job_id, row_number, outcome, resource_id, confirmed_at)
       VALUES (:id, :row, :outcome, :resource, clock_timestamp())',
      ['id' => (string) $id, 'row' => $rowNumber, 'outcome' => $outcome, 'resource' => $resourceId],
    );
  }

  /**
   * Method resetClosedManager
   *
   * Resets the main entity manager only when Doctrine has closed it.
   *
   * @access private
   *
   * @return void
   */
  private function resetClosedManager(): void
  {
    if (!$this->entityManager->isOpen()) {
      $this->registry->resetManager('main');
    }
  }
  // #endregion
}
