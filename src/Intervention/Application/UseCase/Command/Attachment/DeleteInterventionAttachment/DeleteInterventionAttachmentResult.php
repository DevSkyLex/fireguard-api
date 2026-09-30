<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteInterventionAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Returns the deleted attachment and its owning intervention identifiers.
   *
   * @access public
   *
   * @param string $attachmentId identifier of the deleted attachment
   * @param string $interventionId identifier of the owning intervention
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $interventionId,
  ) {
  }
  // #endregion
}
