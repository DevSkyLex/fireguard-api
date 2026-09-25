<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Attachment;

/** Persisted file metadata shared by new and restored intervention attachments. */
final readonly class InterventionAttachmentFile
{
  public function __construct(
    public string $fileName,
    public string $storagePath,
    public string $mimeType,
    public int $size,
  ) {
  }
}
