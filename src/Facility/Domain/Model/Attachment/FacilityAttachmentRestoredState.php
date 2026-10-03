<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use DateTimeImmutable;
use Facility\Domain\ValueObject\PlanCalibration;

/** Timestamp and presentation state restored from a persisted attachment. */
final readonly class FacilityAttachmentRestoredState
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores the upload timestamp, optional creation details, and primary-plan state of an attachment.
   *
   * @access public
   *
   * @param DateTimeImmutable $uploadedAt time the attachment was uploaded
   * @param ?FacilityAttachmentCreationOptions $options creation details retained for the attachment, when available
   * @param bool $isPrimaryPlan whether the attachment is selected as the facility plan
   * @param ?string $calibrationBuildingId original building frame of a confirmed calibration
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $uploadedAt,
    public ?FacilityAttachmentCreationOptions $options = null,
    public bool $isPrimaryPlan = false,
    public ?PlanCalibration $calibration = null,
    public int $revision = 1,
    public ?string $calibrationBuildingId = null,
  ) {
  }
  // #endregion
}
