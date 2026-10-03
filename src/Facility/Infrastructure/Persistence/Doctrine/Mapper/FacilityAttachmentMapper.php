<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Facility\Domain\Model\Attachment\{FacilityAttachment, FacilityAttachmentCreationOptions, FacilityAttachmentRestoredState};
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, PlanCalibration};
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use LogicException;

/**
 * Mapper FacilityAttachmentMapper.
 *
 * @category Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAttachmentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   */
  public static function toDomain(FacilityAttachmentRecord $record): FacilityAttachment
  {
    if (!$record->facility instanceof FacilityRecord) {
      throw new LogicException('Attachment record must reference a facility.');
    }

    return FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString($record->id),
      facilityId: FacilityId::fromString($record->facility->id),
      fileName: $record->fileName,
      storagePath: $record->storagePath,
      mimeType: $record->mimeType,
      size: $record->size,
      state: new FacilityAttachmentRestoredState(
        $record->uploadedAt,
        new FacilityAttachmentCreationOptions($record->label, AttachmentKind::from($record->kind), $record->imageWidth, $record->imageHeight),
        $record->isPrimaryPlan,
        null === $record->calibration ? null : PlanCalibration::fromArray($record->calibration),
        $record->revision,
        $record->calibrationBuildingId,
      ),
    );
  }

  /**
   * Method toRecord.
   *
   * @since 1.0.0
   */
  public static function toRecord(FacilityAttachment $attachment): FacilityAttachmentRecord
  {
    $record = new FacilityAttachmentRecord();
    $record->id = (string) $attachment->id();
    $record->fileName = $attachment->file()->fileName;
    $record->storagePath = $attachment->file()->storagePath;
    $record->mimeType = $attachment->file()->mimeType;
    $record->size = $attachment->file()->size;
    $record->label = $attachment->label();
    $record->uploadedAt = $attachment->file()->uploadedAt;
    $record->kind = $attachment->kind()->value;
    $record->isPrimaryPlan = $attachment->isPrimaryPlan();
    $record->imageWidth = $attachment->imageWidth();
    $record->imageHeight = $attachment->imageHeight();
    $record->calibration = $attachment->calibration()?->toArray();
    $record->calibrationBuildingId = $attachment->calibrationBuildingId();
    $record->revision = $attachment->revision();

    return $record;
  }
  // #endregion
}
