<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Attachment\ListConversationAttachments;

use Messaging\Application\Contract\Attachment\MessagingAttachmentPage;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListConversationAttachmentsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationAttachmentsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachmentPage $page the attachment page
   */
  public function __construct(public MessagingAttachmentPage $page)
  {
  }
  // #endregion
}
