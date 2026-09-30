<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/**
 * File metadata for a new or restored inspection attachment.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionAttachmentFile
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures the file metadata required to create or restore an inspection attachment.
   *
   * @access public
   *
   * @param string $fileName original file name
   * @param string $storagePath path used by the storage adapter
   * @param string $mimeType declared media type of the file
   * @param int $size file size in bytes
   * @param ?string $label optional display label for the attachment
   *
   * @return void
   */
  public function __construct(
    public string $fileName,
    public string $storagePath,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
  ) {
  }
  // #endregion
}
