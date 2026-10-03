<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use DateTimeImmutable;

/**
 * Class FacilityAttachmentFile
 *
 * Holds the immutable stored file metadata independently from plan settings and calibration.
 *
 * @category Model
 */
final readonly class FacilityAttachmentFile
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the exact file metadata used during creation or persisted restoration.
   *
   * @access public
   *
   * @param string $fileName the original file name
   * @param string $storagePath the authenticated storage location
   * @param string $mimeType the stored MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the persisted upload timestamp
   *
   * @return void
   */
  public function __construct(
    public string $fileName,
    public string $storagePath,
    public string $mimeType,
    public int $size,
    public DateTimeImmutable $uploadedAt,
  ) {
  }
  // #endregion
}
