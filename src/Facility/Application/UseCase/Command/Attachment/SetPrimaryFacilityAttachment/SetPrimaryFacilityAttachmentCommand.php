<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SetPrimaryFacilityAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetPrimaryFacilityAttachmentCommand implements CommandMessage
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
