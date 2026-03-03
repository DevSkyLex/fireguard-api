<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteAttachmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
