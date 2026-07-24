<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInspectionAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionAttachmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
