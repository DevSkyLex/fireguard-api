<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Service;

/**
 * DTO UploadedCsv.
 *
 * Presentation-layer read model for a validated multipart CSV upload,
 * produced by {@see CsvUploadGuard} and consumed by `CreateImportJobProcessor`.
 * Mirrors `Shared\Presentation\Api\Attachment\UploadedAttachment`.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadedCsv
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $kind the requested import kind value (`equipment`|`facility`)
   * @param string $fileName the original file name
   * @param string $contents the file contents
   * @param string $mimeType the sniffed MIME type
   * @param int $size the file size in bytes
   * @param bool $dryRun whether the job validates and reports without provisioning anything
   */
  public function __construct(
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
