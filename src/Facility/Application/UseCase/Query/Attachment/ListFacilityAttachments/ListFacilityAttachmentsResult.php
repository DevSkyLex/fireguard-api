<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListFacilityAttachmentsResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilityAttachmentsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, fileName: string, mimeType: string, size: int, label: ?string, uploadedAt: string, kind: string, isPrimaryPlan: bool, imageWidth: ?int, imageHeight: ?int, calibration: ?array{widthMeters: float, rotationDegrees: float, offsetXMeters: float, offsetZMeters: float}, calibrationBuildingId: ?string, calibrationIssue?: 'building_changed'|'unverified_frame'|null, revision: int}> $attachments
   */
  public function __construct(
    public array $attachments,
  ) {
  }
  // #endregion
}
