<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddFacilityAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddFacilityAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the stored facility attachment metadata, including image dimensions and plan status when available.
   *
   * @access public
   *
   * @param string $attachmentId identifier assigned to the stored attachment
   * @param string $facilityId facility that owns the attachment
   * @param string $fileName stored file name
   * @param string $mimeType stored media type
   * @param int $size file size in bytes
   * @param ?string $label optional saved user-facing label
   * @param DateTimeImmutable $uploadedAt time the attachment was recorded as uploaded
   * @param string $kind attachment classification
   * @param bool $isPrimaryPlan whether the attachment is currently selected as the facility primary plan
   * @param ?int $imageWidth image width in pixels, when image metadata is available
   * @param ?int $imageHeight image height in pixels, when image metadata is available
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $facilityId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
    public string $kind = 'document',
    public bool $isPrimaryPlan = false,
    public ?int $imageWidth = null,
    public ?int $imageHeight = null,
  ) {
  }
  // #endregion
}
