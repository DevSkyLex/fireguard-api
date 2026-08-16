<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\CreateImportJob;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateImportJobResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateImportJobResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $importJobId the created import job identifier
   * @param string $organizationId the owning organization identifier
   * @param string $kind the import kind value
   * @param string $status the import job status value
   * @param string $originalFilename the original uploaded file name
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param bool $dryRun whether the job validates and reports without provisioning anything
   */
  public function __construct(
    public string $importJobId,
    public string $organizationId,
    public string $kind,
    public string $status,
    public string $originalFilename,
    public DateTimeImmutable $createdAt,
    public bool $dryRun = false,
  ) {
  }
  // #endregion
}
