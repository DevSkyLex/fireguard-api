<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use LogicException;

/**
 * Mapper FacilityAttachmentMapper.
 *
 * @category Mapper
 *
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
      uploadedAt: $record->uploadedAt,
      label: $record->label,
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
    $record->fileName = $attachment->fileName();
    $record->storagePath = $attachment->storagePath();
    $record->mimeType = $attachment->mimeType();
    $record->size = $attachment->size();
    $record->label = $attachment->label();
    $record->uploadedAt = $attachment->uploadedAt();

    return $record;
  }
  // #endregion
}
