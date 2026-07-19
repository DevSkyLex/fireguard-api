<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteFacilityAttachmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityAttachmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $attachmentId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
