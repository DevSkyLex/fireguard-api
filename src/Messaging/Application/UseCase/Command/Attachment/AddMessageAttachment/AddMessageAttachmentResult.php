<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddMessageAttachmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddMessageAttachmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $attachmentId,
    public string $messageId,
    public string $conversationId,
    public string $organizationId,
    public string $uploadedByMemberId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
  ) {
  }
  // #endregion
}
