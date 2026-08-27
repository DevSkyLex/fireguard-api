<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportJob;

use DateTimeImmutable;
use Import\Domain\Model\ImportJob\ImportJob;
use Shared\Application\Message\ResultMessage;

use function array_map;

/**
 * UseCase GetImportJobResult.
 *
 * Also reused as the per-item shape by
 * `Import\Application\UseCase\Query\ListImportJobs\ListImportJobsResult`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetImportJobResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $importJobId the import job identifier
   * @param string $organizationId the owning organization identifier
   * @param string $kind the import kind value (`equipment`|`facility`)
   * @param string $status the import job status value
   * @param string $originalFilename the original uploaded file name
   * @param bool $dryRun whether the job validated and reported without provisioning anything
   * @param ?int $totalRows the total data row count, once counted
   * @param int $processedRows the number of data rows processed so far
   * @param int $successfulRows the number of rows successfully provisioned (or, for a dry run, that would be)
   * @param int $failedRows the number of rows reported as failed
   * @param list<array{rowNumber: int, column: ?string, code: string, message: string}> $errorReport the per-row report
   * @param ?string $jobError the catastrophic failure reason, when failed
   * @param string $createdBy the creating user identifier
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param ?DateTimeImmutable $startedAt when processing started
   * @param ?DateTimeImmutable $completedAt when the job reached a terminal state
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  public function __construct(
    public string $importJobId,
    public string $organizationId,
    public string $kind,
    public string $status,
    public string $originalFilename,
    public bool $dryRun,
    public ?int $totalRows,
    public int $processedRows,
    public int $successfulRows,
    public int $failedRows,
    public array $errorReport,
    public ?string $jobError,
    public string $createdBy,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $startedAt,
    public ?DateTimeImmutable $completedAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the read view from the domain aggregate.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   *
   * @return self the read view
   */
  public static function fromDomain(ImportJob $job): self
  {
    return new self(
      importJobId: (string) $job->id(),
      organizationId: $job->organizationId(),
      kind: $job->kind()->value,
      status: $job->status()->value,
      originalFilename: $job->originalFilename(),
      dryRun: $job->isDryRun(),
      totalRows: $job->totalRows(),
      processedRows: $job->processedRows(),
      successfulRows: $job->successfulRows(),
      failedRows: $job->failedRows(),
      errorReport: array_map(
        static fn ($error): array => [
          'rowNumber' => $error->rowNumber,
          'column' => $error->column,
          'code' => $error->code,
          'message' => $error->message,
        ],
        $job->errorReport(),
      ),
      jobError: $job->jobError(),
      createdBy: $job->createdBy(),
      createdAt: $job->createdAt(),
      startedAt: $job->startedAt(),
      completedAt: $job->completedAt(),
      updatedAt: $job->updatedAt(),
    );
  }
  // #endregion
}
