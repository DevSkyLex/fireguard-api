<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteInspectionAttachmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionAttachmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $attachmentId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
