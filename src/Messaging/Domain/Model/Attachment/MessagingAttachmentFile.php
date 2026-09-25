<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Attachment;

/**
 * File metadata carried by an attachment, independently of its owner.
 */
final readonly class MessagingAttachmentFile
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
