<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration;

use Shared\Application\Message\ResultMessage;

/**
 * Result SetFacilityAttachmentCalibrationResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetFacilityAttachmentCalibrationResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float} $calibration
   * @param ?string $calibrationBuildingId original building frame confirmed by the server
   * @param 'building_changed'|'unverified_frame'|null $calibrationIssue current usability of the retained frame
   */
  public function __construct(
    public string $id,
    public string $facilityId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public int $revision,
    public string $kind,
    public bool $isPrimaryPlan,
    public ?int $imageWidth,
    public ?int $imageHeight,
    public string $uploadedAt,
    public ?array $calibration,
    public ?string $calibrationBuildingId = null,
    public ?string $calibrationIssue = null,
  ) {
  }
  // #endregion
}
