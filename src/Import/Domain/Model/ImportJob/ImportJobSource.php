<?php

declare(strict_types=1);

namespace Import\Domain\Model\ImportJob;

use Import\Domain\ValueObject\{ImportJobId, ImportKind};

/** Identity and uploaded source of a persisted import job. */
final readonly class ImportJobSource
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the import job identity, uploaded source, organization, and initiating actor.
   *
   * @access public
   *
   * @param ImportJobId $id identifier assigned to the import job
   * @param string $organizationId organization owning the imported data
   * @param ImportKind $kind import workflow selected for the source
   * @param string $storagePath storage key for the uploaded file
   * @param string $originalFilename client-provided file name retained for reporting
   * @param string $createdBy user who initiated the job
   * @param bool $dryRun whether the import was created as a simulation
   *
   * @return void
   */
  public function __construct(
    public ImportJobId $id,
    public string $organizationId,
    public ImportKind $kind,
    public string $storagePath,
    public string $originalFilename,
    public string $createdBy,
    public bool $dryRun,
  ) {
  }
  // #endregion
}
