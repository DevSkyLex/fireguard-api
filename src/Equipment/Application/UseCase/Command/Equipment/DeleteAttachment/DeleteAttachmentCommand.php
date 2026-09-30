<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies an attachment and its owning equipment within an organization for deletion.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the equipment
   * @param string $equipmentId equipment owning the attachment
   * @param string $attachmentId attachment to remove
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
