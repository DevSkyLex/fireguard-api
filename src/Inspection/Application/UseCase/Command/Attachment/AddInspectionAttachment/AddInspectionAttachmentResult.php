<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddInspectionAttachmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInspectionAttachmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $attachmentId,
    public string $inspectionId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $nonConformityId,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
  ) {
  }
  // #endregion
}
