<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInterventionAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionAttachmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $userId,
    public string $interventionId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
