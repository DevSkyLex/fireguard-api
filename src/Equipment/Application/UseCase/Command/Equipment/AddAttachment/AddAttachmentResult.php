<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddAttachmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddAttachmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $attachmentId,
    public string $equipmentId,
    public string $fileName,
    public string $storagePath,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
  ) {
  }
  // #endregion
}
