<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteAttachmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteAttachmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $attachmentId,
    public string $equipmentId,
  ) {
  }
  // #endregion
}
