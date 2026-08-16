<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Command\CreateImportJob;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateImportJobCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateImportJobCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user identifier
   * @param string $organizationId the owning organization identifier
   * @param string $kind the import kind value (`equipment`|`facility`)
   * @param string $fileName the original uploaded file name
   * @param string $contents the uploaded CSV file contents
   * @param string $mimeType the uploaded file's sniffed MIME type
   * @param int $size the uploaded file size in bytes
   * @param bool $dryRun whether the job validates and reports without provisioning anything
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $kind,
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public bool $dryRun = false,
  ) {
  }
  // #endregion
}
