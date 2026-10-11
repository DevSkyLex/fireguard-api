<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Attachment;

/**
 * Contract FacilityAttachmentView.
 *
 * Carries attachment metadata without exposing persistence or domain objects.
 *
 * @category Contract
 */
final readonly class FacilityAttachmentView
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float} $calibration the optional calibration snapshot
   * @param 'building_changed'|'unverified_frame'|null $calibrationIssue the frame diagnostic
   */
  public function __construct(
    public string $id,
    public string $facilityId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public string $uploadedAt,
    public string $kind,
    public bool $isPrimaryPlan,
    public ?int $imageWidth,
    public ?int $imageHeight,
    public ?array $calibration,
    public ?string $calibrationBuildingId,
    public ?string $calibrationIssue,
    public int $revision,
  ) {
  }
  // #endregion
}
