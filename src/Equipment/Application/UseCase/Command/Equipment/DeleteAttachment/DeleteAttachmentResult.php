<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the identifiers of the attachment removed from the equipment.
   *
   * @access public
   *
   * @param string $attachmentId identifier of the removed attachment
   * @param string $equipmentId equipment that owned the attachment
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $equipmentId,
  ) {
  }
  // #endregion
}
