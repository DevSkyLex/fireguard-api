<?php

declare(strict_types=1);

namespace Import\Domain\Event;

use DateTimeImmutable;

/**
 * Event ImportJobCompletedEvent.
 *
 * Raised by `ProcessImportJobHandler` when an import batch runs to
 * completion — even when every row failed, since the batch itself
 * completed (partial success). Recorded in the audit ledger as
 * `import.job_completed` (counts only, never row payloads).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ImportJobCompletedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $importJobId the import job identifier
   * @param string $organizationId the organization identifier
   * @param string $kind the provisioned resource kind (`equipment`|`facility`)
   * @param int $totalRows the total data row count
   * @param int $successfulRows the number of rows successfully provisioned
   * @param int $failedRows the number of rows reported as failed
   * @param string $createdBy the user identifier who created the job
   */
  public function __construct(
    public string $importJobId,
    public string $organizationId,
    public string $kind,
    public int $totalRows,
    public int $successfulRows,
    public int $failedRows,
    public string $createdBy,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
