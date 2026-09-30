<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInspectionAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the inspection attachment to remove within its organization.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the inspection
   * @param string $inspectionId inspection owning the attachment
   * @param string $attachmentId attachment to remove
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
