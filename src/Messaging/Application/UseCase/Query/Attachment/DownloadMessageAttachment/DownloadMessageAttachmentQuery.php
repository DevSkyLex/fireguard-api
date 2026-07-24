<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase DownloadMessageAttachmentQuery.
 *
 * Backs the attachment content download (`GET /messaging-attachments/{id}/content`):
 * fetches an attachment's stored bytes for a caller who may read the owning
 * conversation.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DownloadMessageAttachmentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $attachmentId the attachment id value
   */
  public function __construct(
    public string $userId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
