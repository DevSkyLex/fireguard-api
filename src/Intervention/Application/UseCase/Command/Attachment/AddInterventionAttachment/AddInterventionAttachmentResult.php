<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddInterventionAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInterventionAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Returns the persisted attachment metadata after an upload.
   *
   * @access public
   *
   * @param string $attachmentId persisted attachment identifier
   * @param string $interventionId intervention owning the attachment
   * @param string $fileName original file name
   * @param string $mimeType persisted media type
   * @param int $size persisted file size in bytes
   * @param ?string $label optional display label
   * @param DateTimeImmutable $uploadedAt time the attachment was uploaded
   * @param ?string $workItemId optional linked work item identifier
   * @param string $kind persisted attachment kind
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $interventionId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
    public ?string $workItemId = null,
    public string $kind = 'file',
  ) {
  }
  // #endregion
}
