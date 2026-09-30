<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

/** File metadata restored from the equipment attachment record. */
final readonly class RestoredEquipmentAttachmentFile
{
  /**
   * Method __construct
   *
   * Restores the file metadata associated with a persisted equipment attachment.
   *
   * @access public
   *
   * @param string $fileName stored file name
   * @param string $storagePath storage key for the file bytes
   * @param string $mimeType media type recorded for the file
   * @param int $size file size in bytes
   * @param ?string $label optional user-facing attachment label
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
}
