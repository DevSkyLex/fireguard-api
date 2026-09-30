<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Attachment;

/** Persisted file metadata shared by new and restored intervention attachments. */
final readonly class InterventionAttachmentFile
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures persisted file metadata for a new or restored attachment.
   *
   * @access public
   *
   * @param string $fileName original file name
   * @param string $storagePath path used by the storage adapter
   * @param string $mimeType declared media type
   * @param int $size file size in bytes
   *
   * @return void
   */
  public function __construct(
    public string $fileName,
    public string $storagePath,
    public string $mimeType,
    public int $size,
  ) {
  }
  // #endregion
}
