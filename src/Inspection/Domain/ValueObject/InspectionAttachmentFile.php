<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/**
 * File metadata for a new or restored inspection attachment.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionAttachmentFile
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
