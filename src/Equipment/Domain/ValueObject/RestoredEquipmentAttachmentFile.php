<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

/** File metadata restored from the equipment attachment record. */
final readonly class RestoredEquipmentAttachmentFile
{
  public function __construct(
    public string $fileName,
    public string $storagePath,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
  ) {
  }
}
