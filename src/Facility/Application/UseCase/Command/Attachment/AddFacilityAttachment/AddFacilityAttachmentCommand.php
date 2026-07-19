<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddFacilityAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddFacilityAttachmentCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
    public ?string $attachmentId = null,
  ) {
  }
  // #endregion
}
