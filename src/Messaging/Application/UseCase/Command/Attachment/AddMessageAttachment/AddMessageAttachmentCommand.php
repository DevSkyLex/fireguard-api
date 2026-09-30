<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddMessageAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddMessageAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries the caller, message target and uploaded file data.
   *
   * @access public
   *
   * @param string $userId user uploading the attachment
   * @param string $messageId message receiving the attachment
   * @param string $fileName original uploaded file name
   * @param string $contents uploaded file contents
   * @param string $mimeType declared media type of the upload
   * @param int $size uploaded file size in bytes
   * @param ?string $label optional display label
   * @param ?string $attachmentId optional caller-supplied attachment identifier
   *
   * @return void
   */
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
