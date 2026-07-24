<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteFacilityAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityAttachmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
