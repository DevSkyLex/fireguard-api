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

/** Lease fencing, row locks and receipts share the main business connection. */
final readonly class PostgresImportExecutionAdapter implements ImportExecutionPort
{
  public function __construct(
    private Connection $connection,
    private EntityManagerInterface $entityManager,
    private ManagerRegistry $registry,
    private ImportJobRepositoryPort $repository,
  ) {
  }

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
    $job = $this->repository->findById($id);
    if (0 === $claimed && null !== $job && !$job->status()->isTerminal()) {
      throw new ImportLeaseUnavailable();
    }

    return $claimed > 0 ? $job : null;
  }

  /**
   * @param callable(ImportJob):?string $operation
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
        $job = $this->repository->findById($id) ?? throw new LogicException('The locked import no longer exists.');
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

  public function release(ImportJobId $id, string $owner): void
  {
    $this->connection->executeStatement(
      'UPDATE import_jobs SET lease_owner = NULL, lease_expires_at = NULL WHERE id = :id AND lease_owner = :owner',
      ['id' => (string) $id, 'owner' => $owner],
    );
  }

  public function canResume(ImportJobId $id): bool
  {
    return false !== $this->connection->fetchOne(
      "SELECT id FROM import_jobs WHERE id = :id AND status <> 'completed'
       AND (lease_expires_at IS NULL OR lease_expires_at <= clock_timestamp())",
      ['id' => (string) $id],
    );
  }

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
      $job = $this->repository->findById($id) ?? throw ImportJobNotFoundException::withId((string) $id);
      $job->resume(new DateTimeImmutable());
      $this->repository->save($job);
      $this->connection->executeStatement('UPDATE import_jobs SET lease_owner = NULL, lease_expires_at = NULL WHERE id = :id', ['id' => (string) $id]);
      $enqueue($job);

      return $job;
    });
  }

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

  private function resetClosedManager(): void
  {
    if (!$this->entityManager->isOpen()) {
      $this->registry->resetManager('main');
    }
  }
}
