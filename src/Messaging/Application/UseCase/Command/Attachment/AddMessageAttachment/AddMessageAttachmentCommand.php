<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddMessageAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddMessageAttachmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $userId,
    public string $messageId,
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
    public ?string $attachmentId = null,
  ) {
  }
  // #endregion
}
