<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase SetPrimaryFacilityAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetPrimaryFacilityAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the selected primary-plan attachment and its stored file metadata.
   *
   * @access public
   *
   * @param string $attachmentId identifier of the selected primary-plan attachment
   * @param string $facilityId facility owning the attachment
   * @param string $fileName stored file name
   * @param string $mimeType stored media type
   * @param int $size file size in bytes
   * @param ?string $label user-facing attachment label, when present
   * @param string $kind attachment classification
   * @param bool $isPrimaryPlan whether the attachment is selected as the facility primary plan
   * @param ?int $imageWidth image width in pixels, when available
   * @param ?int $imageHeight image height in pixels, when available
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
    public string $kind,
    public bool $isPrimaryPlan,
    public ?int $imageWidth,
    public ?int $imageHeight,
  ) {
  }
  // #endregion
}
