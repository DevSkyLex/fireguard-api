<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteInterventionAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the caller and attachment to delete from an intervention.
   *
   * @access public
   *
   * @param string $userId user requesting deletion
   * @param string $interventionId intervention expected to own the attachment
   * @param string $attachmentId attachment identifier to delete
   *
   * @return void
   */
  public function __construct(
    public string $userId,
    public string $interventionId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
