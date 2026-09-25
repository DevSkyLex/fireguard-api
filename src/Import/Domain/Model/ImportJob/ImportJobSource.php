<?php

declare(strict_types=1);

namespace Import\Domain\Model\ImportJob;

use Import\Domain\ValueObject\{ImportJobId, ImportKind};

/** Identity and uploaded source of a persisted import job. */
final readonly class ImportJobSource
{
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
}
