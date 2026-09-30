<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteMessageAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMessageAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the caller and attachment to delete.
   *
   * @access public
   *
   * @param string $userId user requesting deletion
   * @param string $attachmentId attachment identifier to delete
   *
   * @return void
   */
  public function __construct(
    public string $userId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
