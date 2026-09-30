<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteInspectionAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the identifiers of the removed inspection attachment.
   *
   * @access public
   *
   * @param string $attachmentId identifier of the removed attachment
   * @param string $inspectionId inspection that owned the attachment
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
