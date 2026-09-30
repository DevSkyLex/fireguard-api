<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteFacilityAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the identifiers of the removed facility attachment.
   *
   * @access public
   *
   * @param string $attachmentId identifier of the removed attachment
   * @param string $facilityId facility that owned the attachment
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
