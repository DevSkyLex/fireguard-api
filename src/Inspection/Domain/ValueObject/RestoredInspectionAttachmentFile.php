<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/**
 * File metadata restored exactly from an inspection attachment row.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredInspectionAttachmentFile
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
