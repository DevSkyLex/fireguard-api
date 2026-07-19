<?php

declare(strict_types=1);

namespace Import\Domain\Event;

use DateTimeImmutable;

/**
 * Event ImportJobFailedEvent.
 *
 * Raised by `ProcessImportJobHandler` when an import batch fails
 * catastrophically (the uploaded CSV blob could not be read, or the file
 * itself is unreadable/malformed) — as opposed to individual row failures,
 * which are non-fatal and reported instead. Recorded in the audit ledger as
 * `import.job_failed`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ImportJobFailedEvent
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
   * @param string $jobError the catastrophic failure reason
   * @param string $createdBy the user identifier who created the job
   */
  public function __construct(
    public string $importJobId,
    public string $organizationId,
    public string $kind,
    public string $jobError,
    public string $createdBy,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
