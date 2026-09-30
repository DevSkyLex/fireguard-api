<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddInterventionAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInterventionAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries the caller, target and uploaded file data for an attachment write.
   *
   * @access public
   *
   * @param string $userId user performing the attachment operation
   * @param string $interventionId intervention receiving the attachment
   * @param string $fileName original uploaded file name
   * @param string $contents uploaded file contents
   * @param string $mimeType declared media type of the upload
   * @param int $size uploaded file size in bytes
   * @param ?string $label optional display label
   * @param ?string $attachmentId optional caller-supplied attachment identifier
   * @param ?string $workItemId optional work item linked to the attachment
   * @param string $kind attachment kind, defaulting to a regular file
   *
   * @return void
   */
  public function __construct(
    public string $userId,
    public string $interventionId,
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
    public ?string $attachmentId = null,
    public ?string $workItemId = null,
    public string $kind = 'file',
  ) {
  }
  // #endregion
}
