<?php

declare(strict_types=1);

namespace Import\Domain\Model\ImportJob;

use Import\Domain\ValueObject\{ImportRowError, ImportStatus};

/** Persisted lifecycle and row counters of an import job. */
final readonly class ImportJobProgress
{
  /**
   * @param list<ImportRowError> $errorReport
   */
  public function __construct(
    public ImportStatus $status,
    public ?int $totalRows,
    public int $processedRows,
    public int $successfulRows,
    public int $failedRows,
    public array $errorReport,
    public ?string $jobError,
  ) {
  }
}
