<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\Attachment;

use DateTimeImmutable;
use Facility\Domain\Model\Attachment\{FacilityAttachment, FacilityAttachmentCreationOptions, FacilityAttachmentRestoredState};
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, PlanCalibration};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(FacilityAttachment::class)]
final class FacilityAttachmentCalibrationFrameTest extends TestCase
{
  // #region Constants
  private const string OLD_BUILDING_ID = '550e8400-e29b-41d4-a716-446655465001';

  private const string NEW_BUILDING_ID = '550e8400-e29b-41d4-a716-446655465002';
  // #endregion

  // #region Methods
  /**
   * Method confirmationRecordsServerBuildingFrame
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function confirmationRecordsServerBuildingFrame(): void
  {
    $attachment = $this->attachment();
    $attachment->calibrate($this->calibration(), self::OLD_BUILDING_ID);

    self::assertSame(self::OLD_BUILDING_ID, $attachment->calibrationBuildingId());
    self::assertSame($this->calibration()->toArray(), $attachment->calibration()?->toArray());
    self::assertSame(2, $attachment->revision());
  }

  /**
   * Method unrelatedAttachmentMutationsPreserveOriginalFrame
   *
   * Restoring and changing the primary-plan flag cannot infer a different coordinate frame.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function unrelatedAttachmentMutationsPreserveOriginalFrame(): void
  {
    $attachment = $this->attachment($this->calibration(), self::OLD_BUILDING_ID);
    $attachment->markAsPrimary();
    $attachment->clearPrimary();

    self::assertSame(self::OLD_BUILDING_ID, $attachment->calibrationBuildingId());
    self::assertSame($this->calibration()->toArray(), $attachment->calibration()?->toArray());
    self::assertSame(1, $attachment->revision());
  }

  /**
   * Method reconfirmingAfterMoveChangesOnlyFrameAndRevision
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function reconfirmingAfterMoveChangesOnlyFrameAndRevision(): void
  {
    $attachment = $this->attachment($this->calibration(), self::OLD_BUILDING_ID);
    $attachment->calibrate($this->calibration(), self::NEW_BUILDING_ID);

    self::assertSame(self::NEW_BUILDING_ID, $attachment->calibrationBuildingId());
    self::assertSame($this->calibration()->toArray(), $attachment->calibration()?->toArray());
    self::assertSame(2, $attachment->revision());
  }

  /**
   * Method clearingCalibrationAlsoClearsProvenance
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function clearingCalibrationAlsoClearsProvenance(): void
  {
    $attachment = $this->attachment($this->calibration(), self::OLD_BUILDING_ID);
    $attachment->calibrate(null, self::NEW_BUILDING_ID);

    self::assertNull($attachment->calibration());
    self::assertNull($attachment->calibrationBuildingId());
    self::assertSame(2, $attachment->revision());
  }

  /**
   * Method unavailableOriginalFrameRemainsUnverified
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function unavailableOriginalFrameRemainsUnverified(): void
  {
    $attachment = $this->attachment($this->calibration());

    self::assertNotNull($attachment->calibration());
    self::assertNull($attachment->calibrationBuildingId());
  }

  /**
   * Method attachment
   *
   * @access private
   *
   * @param ?PlanCalibration $calibration retained image calibration
   * @param ?string $buildingId original confirmed building identifier
   *
   * @return FacilityAttachment restored floor-plan image
   */
  private function attachment(?PlanCalibration $calibration = null, ?string $buildingId = null): FacilityAttachment
  {
    return FacilityAttachment::reconstitute(
      FacilityAttachmentId::fromString('550e8400-e29b-41d4-a716-446655465003'),
      FacilityId::fromString('550e8400-e29b-41d4-a716-446655465004'),
      'floor.png',
      'floor.png',
      'image/png',
      1024,
      new FacilityAttachmentRestoredState(
        new DateTimeImmutable(),
        new FacilityAttachmentCreationOptions(kind: AttachmentKind::FLOOR_PLAN, imageWidth: 1000, imageHeight: 500),
        calibration: $calibration,
        calibrationBuildingId: $buildingId,
      ),
    );
  }

  /**
   * Method calibration
   *
   * @access private
   *
   * @return PlanCalibration image calibration retained across hierarchy moves
   */
  private function calibration(): PlanCalibration
  {
    return PlanCalibration::fromArray(['widthMeters' => 20.0, 'rotationDegrees' => 30.0, 'offsetXMeters' => -2.0, 'offsetZMeters' => 4.0]);
  }
  // #endregion
}
