<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\ProcessImportJob;

use Import\Application\Contract\{ImportRowInput, ImportRowValidationException, ImportSessionContext};
use Import\Application\Port\Outbound\{CsvRowStreamerPort, ImportRowSessionPort};
use Import\Application\Port\Outbound\Internal\{ImportExecutionPort, ImportJobRepositoryPort, ImportKindRegistryPort};
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportRowError};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, FileStoragePort};
use Shared\Application\Port\Outbound\{LoggerPort, UuidGeneratorPort};
use Throwable;

/**
 * UseCase ProcessImportJobHandler.
 *
 * Claims the retained job, parses rows and applies owner-contributed row outcomes.
 * Leases, row transactions, receipts and reports remain owned by Import; each
 * contributor owns a fresh execution session and simulation reconstruction.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProcessImportJobHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ImportExecutionPort $execution exclusive leases and atomic row work units
   * @param FileStoragePort $fileStorage the file storage port
   * @param CsvRowStreamerPort $csvStreamer the CSV row streamer port
   * @param ImportKindRegistryPort $kinds registered owner contributions
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param ClockPort $clock the clock port
   * @param LoggerPort $logger the logger
   */
  public function __construct(
    private ImportExecutionPort $execution,
    private FileStoragePort $fileStorage,
    private CsvRowStreamerPort $csvStreamer,
    private ImportKindRegistryPort $kinds,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
    private LoggerPort $logger,
    private UuidGeneratorPort $ids,
    private ImportJobRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ProcessImportJobCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(ProcessImportJobCommand $command): VoidResult
  {
    $this->processIfAvailable($command);

    return new VoidResult();
  }

  /**
   * Skip stale, unauthorized or already claimed jobs before opening a lease.
   *
   * @since 1.0.0
   */
  private function processIfAvailable(ProcessImportJobCommand $command): void
  {
    $id = ImportJobId::fromString($command->importJobId);
    $existing = $this->repository->findForExecution($id);
    if (null === $existing || $existing->status()->isTerminal()) {
      return;
    }
    $actor = $command->requestedBy ?? $existing->createdBy();
    $processor = $this->kinds->find($existing->kind()->value);
    if (null === $processor || !$this->authorization->resolveAccess($actor, $existing->organizationId(), $processor->descriptor()->writePermission)->isGranted()) {
      // A stale queued command must not change the job after its actor loses
      // access. Another authorized member can explicitly resume the same job.
      $this->logger->warning('Import processing skipped after access was removed.', ['import_job_id' => $command->importJobId]);

      return;
    }

    $owner = $this->ids->generate();
    $job = $this->execution->claim($id, $owner);
    if (null === $job) {
      return;
    }

    $this->processClaimedJob($command, $id, $job, $owner, $actor);
  }

  /**
   * Keep the claimed job's lease until processing or failure has completed.
   *
   * @since 1.0.0
   */
  private function processClaimedJob(ProcessImportJobCommand $command, ImportJobId $id, ImportJob $job, string $owner, string $actor): void
  {
    try {
      try {
        $contents = $this->fileStorage->read($job->storagePath());
      } catch (Throwable $exception) {
        $this->fail($id, $owner, 'Unable to read the uploaded CSV file.');

        return;
      }

      try {
        $total = $this->csvStreamer->countDataRows($contents);
      } catch (InvalidArgumentException $exception) {
        $this->fail($id, $owner, $exception->getMessage());

        return;
      }
      $job = $this->execution->run($id, $owner, static function (ImportJob $current) use ($total): ?string {
        $current->setTotalRows($total);

        return null;
      });
      $this->processRows($job, $owner, $contents, $actor);
      $this->execution->run($id, $owner, function (ImportJob $current): ?string {
        $current->complete($this->clock->now());
        $this->eventDispatcher->dispatch(new ImportJobCompletedEvent(
          importJobId: (string) $current->id(),
          organizationId: $current->organizationId(),
          kind: $current->kind()->value,
          totalRows: $current->totalRows() ?? 0,
          successfulRows: $current->successfulRows(),
          failedRows: $current->failedRows(),
          createdBy: $current->createdBy(),
        ));

        return null;
      });
    } catch (Throwable $exception) {
      $this->logger->error('Import processing interrupted; confirmed rows are retained.', [
        'import_job_id' => $command->importJobId, 'exception' => $exception::class,
      ]);

      throw $exception;
    } finally {
      // Technical errors propagate to Messenger. Confirmed rows remain intact;
      // retries use the same job, while a stopped process loses its lease by TTL.
      $this->execution->release($id, $owner);
    }
  }

  /**
   * Method processRows.
   *
   * Reconstructs the simulation projection from confirmed outcomes before
   * continuing. Every new row commits its creation and receipt together.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   * @param string $contents the uploaded CSV file contents
   */
  private function processRows(ImportJob $job, string $owner, string $contents, string $actor): void
  {
    $resumeFrom = $job->processedRows();
    $session = $this->kinds->get($job->kind()->value)->startSession(new ImportSessionContext(
      (string) $job->id(),
      $job->organizationId(),
      $actor,
      $job->isDryRun(),
    ));
    $wouldCreate = [];
    foreach ($this->repository->confirmedSimulationRows($job->id()) as $rowNumber) {
      $wouldCreate[$rowNumber] = true;
    }
    foreach ($job->errorReport() as $report) {
      if ('would_create' === $report->code) {
        $wouldCreate[$report->rowNumber] = true;
      }
    }

    foreach ($this->csvStreamer->rows($contents) as $rowNumber => $row) {
      if ($rowNumber <= $resumeFrom) {
        if ($job->isDryRun() && isset($wouldCreate[$rowNumber])) {
          $session->restoreConfirmedSimulationRow(new ImportRowInput($rowNumber, $row));
        }

        continue;
      }
      $this->execution->run(
        $job->id(),
        $owner,
        fn (ImportJob $current): ?string => $this->processRow($current, new ImportRowInput($rowNumber, $row), $session),
        $rowNumber,
      );
    }
  }

  /**
   * Method processRow
   *
   * Applies a contributor result inside the same transaction as the row receipt.
   * Technical exceptions propagate; structural rejections remain confirmed outcomes.
   *
   * @access private
   *
   * @param ImportJob $job currently locked aggregate
   * @param ImportRowInput $input original decoded source row
   * @param ImportRowSessionPort $session isolated owner processor
   *
   * @return ?string created resource identity for the durable row receipt
   */
  private function processRow(ImportJob $job, ImportRowInput $input, ImportRowSessionPort $session): ?string
  {
    try {
      $outcome = $session->processRow($input);
    } catch (ImportRowValidationException $exception) {
      $job->recordRowError(new ImportRowError($input->rowNumber, $exception->errorCode, $exception->getMessage(), $exception->column));

      return null;
    }
    $report = null === $outcome->code ? null : new ImportRowError(
      $input->rowNumber,
      $outcome->code,
      $outcome->message ?? '',
      $outcome->column,
    );
    if ($outcome->successful) {
      if ($job->isDryRun()) {
        $report = new ImportRowError($input->rowNumber, 'would_create', $outcome->message ?? 'Would create this resource.');
      }
      $job->recordRowSuccess($report);
    } else {
      $job->recordRowError($report ?? throw new InvalidArgumentException('A rejected import row requires a report.'));
    }

    return $job->isDryRun() ? null : $outcome->resourceId;
  }

  /**
   * Method fail.
   *
   * Marks the job catastrophically failed and dispatches the audit event.
   * Never rethrows: the job row already records the terminal state, so
   * rethrowing would only trigger pointless Messenger retries, matching the
   * Automation handler's failure discipline.
   *
   * @since 1.0.0
   *
   * @param ImportJobId $id the import job identifier
   * @param string $owner the worker's reservation token
   * @param string $error the failure reason
   */
  private function fail(ImportJobId $id, string $owner, string $error): void
  {
    $this->execution->run($id, $owner, function (ImportJob $job) use ($error): ?string {
      $job->fail($error, $this->clock->now());
      $this->eventDispatcher->dispatch(new ImportJobFailedEvent(
        importJobId: (string) $job->id(),
        organizationId: $job->organizationId(),
        kind: $job->kind()->value,
        jobError: $error,
        createdBy: $job->createdBy(),
      ));

      return null;
    });
  }
  // #endregion
}
