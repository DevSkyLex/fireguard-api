<?php

declare(strict_types=1);

namespace Import\Domain\Model\ImportJob;

use DateTimeImmutable;
use Import\Domain\ValueObject\{ImportJobId, ImportKind, ImportRowError, ImportStatus};
use InvalidArgumentException;

/**
 * Model ImportJob.
 *
 * A bulk CSV import batch: uploaded once, streamed row by row by
 * `ProcessImportJobHandler`, provisioning Equipment or Facility resources
 * through their existing Create use cases (quota included). A row failure
 * (validation or quota) is recorded in the error report and does not fail
 * the batch — the job still reaches `completed` with a partial success.
 *
 * State machine: `pending` -> `processing` -> (`completed` | `failed`).
 * Every transition is guarded here; the claim from `pending` to `processing`
 * itself happens through a raw-DBAL conditional UPDATE
 * ({@see \Import\Infrastructure\Persistence\Doctrine\Repository\ImportJobRepository::claim()})
 * so a Messenger redelivery of an already-claimed job is a safe no-op — this
 * aggregate is then reloaded already in `processing` status.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJob
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ImportJobId $id the import job identifier
   * @param string $organizationId the owning organization identifier
   * @param ImportKind $kind the provisioned resource kind
   * @param ImportStatus $status the current lifecycle status
   * @param string $storagePath the uploaded CSV storage key
   * @param string $originalFilename the original (untrusted) uploaded file name
   * @param string $createdBy the user identifier who created the job
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param bool $dryRun whether this job validates and reports without provisioning anything
   * @param ?int $totalRows the total data row count, set once counted
   * @param int $processedRows the number of data rows processed so far (high-water mark)
   * @param int $successfulRows the number of rows successfully provisioned (or, for a dry run, that would be)
   * @param int $failedRows the number of rows reported as failed
   * @param list<ImportRowError> $errorReport the per-row report
   * @param ?string $jobError the catastrophic (whole-file) failure reason, when failed
   * @param ?DateTimeImmutable $startedAt when processing started
   * @param ?DateTimeImmutable $completedAt when the job reached a terminal state
   */
  private function __construct(
    private ImportJobId $id,
    private string $organizationId,
    private ImportKind $kind,
    private ImportStatus $status,
    private string $storagePath,
    private string $originalFilename,
    private string $createdBy,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private bool $dryRun = false,
    private ?int $totalRows = null,
    private int $processedRows = 0,
    private int $successfulRows = 0,
    private int $failedRows = 0,
    private array $errorReport = [],
    private ?string $jobError = null,
    private ?DateTimeImmutable $startedAt = null,
    private ?DateTimeImmutable $completedAt = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new import job in `pending` status.
   *
   * @since 1.0.0
   *
   * @param ImportJobId $id the import job identifier
   * @param string $organizationId the owning organization identifier
   * @param ImportKind $kind the provisioned resource kind
   * @param string $storagePath the uploaded CSV storage key
   * @param string $originalFilename the original uploaded file name
   * @param string $createdBy the creating user identifier
   * @param bool $dryRun whether this job validates and reports without provisioning anything
   *
   * @return self the created import job
   */
  public static function create(
    ImportJobId $id,
    string $organizationId,
    ImportKind $kind,
    string $storagePath,
    string $originalFilename,
    string $createdBy,
    bool $dryRun = false,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      organizationId: $organizationId,
      kind: $kind,
      status: ImportStatus::PENDING,
      storagePath: $storagePath,
      originalFilename: $originalFilename,
      createdBy: $createdBy,
      createdAt: $now,
      updatedAt: $now,
      dryRun: $dryRun,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes an import job aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param ImportJobId $id the import job identifier
   * @param string $organizationId the owning organization identifier
   * @param ImportKind $kind the provisioned resource kind
   * @param ImportStatus $status the current lifecycle status
   * @param string $storagePath the uploaded CSV storage key
   * @param string $originalFilename the original uploaded file name
   * @param string $createdBy the creating user identifier
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param bool $dryRun whether this job validates and reports without provisioning anything
   * @param ?int $totalRows the total data row count
   * @param int $processedRows the number of data rows processed so far
   * @param int $successfulRows the number of rows successfully provisioned (or, for a dry run, that would be)
   * @param int $failedRows the number of rows reported as failed
   * @param list<ImportRowError> $errorReport the per-row report
   * @param ?string $jobError the catastrophic failure reason, when failed
   * @param ?DateTimeImmutable $startedAt when processing started
   * @param ?DateTimeImmutable $completedAt when the job reached a terminal state
   *
   * @return self the reconstituted import job
   */
  public static function reconstitute(
    ImportJobId $id,
    string $organizationId,
    ImportKind $kind,
    ImportStatus $status,
    string $storagePath,
    string $originalFilename,
    string $createdBy,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    bool $dryRun,
    ?int $totalRows,
    int $processedRows,
    int $successfulRows,
    int $failedRows,
    array $errorReport,
    ?string $jobError,
    ?DateTimeImmutable $startedAt,
    ?DateTimeImmutable $completedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      kind: $kind,
      status: $status,
      storagePath: $storagePath,
      originalFilename: $originalFilename,
      createdBy: $createdBy,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      dryRun: $dryRun,
      totalRows: $totalRows,
      processedRows: $processedRows,
      successfulRows: $successfulRows,
      failedRows: $failedRows,
      errorReport: $errorReport,
      jobError: $jobError,
      startedAt: $startedAt,
      completedAt: $completedAt,
    );
  }

  /**
   * Method markProcessing.
   *
   * Transitions a pending job into processing. In the real async flow the
   * `pending` -> `processing` claim happens via a raw-DBAL conditional
   * UPDATE before this aggregate is even loaded (see the class docblock);
   * this method exists for completeness and direct aggregate-level testing.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  public function markProcessing(DateTimeImmutable $now): void
  {
    if (ImportStatus::PENDING !== $this->status) {
      throw new InvalidArgumentException('Only a pending import job can start processing.');
    }

    $this->status = ImportStatus::PROCESSING;
    $this->startedAt = $now;
    $this->touch($now);
  }

  /**
   * Method setTotalRows.
   *
   * Records the total data row count once the CSV file has been counted.
   *
   * @since 1.0.0
   *
   * @param int $totalRows the total data row count
   */
  public function setTotalRows(int $totalRows): void
  {
    $this->assertProcessing();
    $this->totalRows = $totalRows;
    $this->touch();
  }

  /**
   * Method recordRowSuccess.
   *
   * Records one successfully provisioned row. A real (write) run passes no
   * `$report`, keeping the report failures-only as today. A dry-run job
   * passes a `would_create` entry so the same report field carries a full
   * per-row outcome list — see the class-level note on
   * {@see ImportRowError}.
   *
   * @since 1.0.0
   *
   * @param ?ImportRowError $report the `would_create` entry to append, dry-run jobs only
   */
  public function recordRowSuccess(?ImportRowError $report = null): void
  {
    $this->assertProcessing();
    ++$this->processedRows;
    ++$this->successfulRows;
    if (null !== $report) {
      $this->errorReport[] = $report;
    }
    $this->touch();
  }

  /**
   * Method recordRowError.
   *
   * Records one failed row (validation or quota) without failing the batch.
   *
   * @since 1.0.0
   *
   * @param ImportRowError $error the row failure to append
   */
  public function recordRowError(ImportRowError $error): void
  {
    $this->assertProcessing();
    ++$this->processedRows;
    ++$this->failedRows;
    $this->errorReport[] = $error;
    $this->touch();
  }

  /**
   * Method complete.
   *
   * Marks the job completed. Reached even when every row failed — the batch
   * ran to completion, so `completed` (not `failed`) records partial success.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  public function complete(DateTimeImmutable $now): void
  {
    $this->assertProcessing();
    $this->status = ImportStatus::COMPLETED;
    $this->completedAt = $now;
    $this->touch($now);
  }

  /**
   * Method fail.
   *
   * Marks the job failed for a catastrophic (whole-file) reason: the
   * uploaded blob could not be read, or the CSV file itself is unreadable
   * (missing/invalid header). Individual row failures never call this.
   *
   * @since 1.0.0
   *
   * @param string $error the failure reason
   * @param DateTimeImmutable $now the current time
   */
  public function fail(string $error, DateTimeImmutable $now): void
  {
    if ($this->status->isTerminal()) {
      throw new InvalidArgumentException('A terminal import job cannot fail again.');
    }

    $this->status = ImportStatus::FAILED;
    $this->jobError = $error;
    $this->completedAt = $now;
    $this->touch($now);
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): ImportJobId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method kind.
   *
   * @since 1.0.0
   */
  public function kind(): ImportKind
  {
    return $this->kind;
  }

  /**
   * Method isDryRun.
   *
   * @since 1.0.0
   */
  public function isDryRun(): bool
  {
    return $this->dryRun;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   */
  public function status(): ImportStatus
  {
    return $this->status;
  }

  /**
   * Method storagePath.
   *
   * @since 1.0.0
   */
  public function storagePath(): string
  {
    return $this->storagePath;
  }

  /**
   * Method originalFilename.
   *
   * @since 1.0.0
   */
  public function originalFilename(): string
  {
    return $this->originalFilename;
  }

  /**
   * Method createdBy.
   *
   * @since 1.0.0
   */
  public function createdBy(): string
  {
    return $this->createdBy;
  }

  /**
   * Method totalRows.
   *
   * @since 1.0.0
   */
  public function totalRows(): ?int
  {
    return $this->totalRows;
  }

  /**
   * Method processedRows.
   *
   * @since 1.0.0
   */
  public function processedRows(): int
  {
    return $this->processedRows;
  }

  /**
   * Method successfulRows.
   *
   * @since 1.0.0
   */
  public function successfulRows(): int
  {
    return $this->successfulRows;
  }

  /**
   * Method failedRows.
   *
   * @since 1.0.0
   */
  public function failedRows(): int
  {
    return $this->failedRows;
  }

  /**
   * Method errorReport.
   *
   * @since 1.0.0
   *
   * @return list<ImportRowError> the per-row error report
   */
  public function errorReport(): array
  {
    return $this->errorReport;
  }

  /**
   * Method jobError.
   *
   * @since 1.0.0
   */
  public function jobError(): ?string
  {
    return $this->jobError;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * @since 1.0.0
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method startedAt.
   *
   * @since 1.0.0
   */
  public function startedAt(): ?DateTimeImmutable
  {
    return $this->startedAt;
  }

  /**
   * Method completedAt.
   *
   * @since 1.0.0
   */
  public function completedAt(): ?DateTimeImmutable
  {
    return $this->completedAt;
  }

  /**
   * Method assertProcessing.
   *
   * @since 1.0.0
   */
  private function assertProcessing(): void
  {
    if (ImportStatus::PROCESSING !== $this->status) {
      throw new InvalidArgumentException('Rows can only be recorded while the import job is processing.');
    }
  }

  /**
   * Method touch.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $now the current time, or null to use now
   */
  private function touch(?DateTimeImmutable $now = null): void
  {
    $this->updatedAt = $now ?? new DateTimeImmutable();
  }
  // #endregion
}
