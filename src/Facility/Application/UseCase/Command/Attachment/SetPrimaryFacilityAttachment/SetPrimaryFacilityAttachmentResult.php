<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase SetPrimaryFacilityAttachmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetPrimaryFacilityAttachmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $attachmentId,
    public string $facilityId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public string $kind,
    public bool $isPrimaryPlan,
    public ?int $imageWidth,
    public ?int $imageHeight,
  ) {
  }
  // #endregion
}
