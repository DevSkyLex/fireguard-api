<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddMessageAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddMessageAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Returns persisted attachment and conversation metadata after upload.
   *
   * @access public
   *
   * @param string $attachmentId persisted attachment identifier
   * @param string $messageId message owning the attachment
   * @param string $conversationId conversation containing the message
   * @param string $organizationId organization owning the conversation
   * @param string $uploadedByMemberId member who uploaded the file
   * @param string $fileName original file name
   * @param string $mimeType persisted media type
   * @param int $size persisted file size in bytes
   * @param ?string $label optional display label
   * @param DateTimeImmutable $uploadedAt time the attachment was uploaded
   *
   * @return void
   */
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
