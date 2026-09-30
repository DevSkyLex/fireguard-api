<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteMessageAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMessageAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Returns the deleted attachment and its message context.
   *
   * @access public
   *
   * @param string $attachmentId identifier of the deleted attachment
   * @param string $messageId identifier of the message that owned it
   * @param string $conversationId identifier of the containing conversation
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $messageId,
    public string $conversationId,
  ) {
  }
  // #endregion
}
