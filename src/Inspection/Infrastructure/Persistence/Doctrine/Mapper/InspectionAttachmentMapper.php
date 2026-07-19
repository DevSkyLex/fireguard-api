<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, NonConformityId};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord, NonConformityRecord};
use LogicException;

/**
 * Mapper InspectionAttachmentMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionAttachmentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   */
  public static function toDomain(InspectionAttachmentRecord $record): InspectionAttachment
  {
    if (!$record->inspection instanceof InspectionRecord) {
      throw new LogicException('Attachment record must reference an inspection.');
    }

    return InspectionAttachment::reconstitute(
      id: InspectionAttachmentId::fromString($record->id),
      inspectionId: InspectionId::fromString($record->inspection->id),
      fileName: $record->fileName,
      storagePath: $record->storagePath,
      mimeType: $record->mimeType,
      size: $record->size,
      uploadedAt: $record->uploadedAt,
      nonConformityId: $record->nonConformity instanceof NonConformityRecord
        ? NonConformityId::fromString($record->nonConformity->id)
        : null,
      label: $record->label,
    );
  }

  /**
   * Method toRecord.
   *
   * @since 1.0.0
   */
  public static function toRecord(InspectionAttachment $attachment): InspectionAttachmentRecord
  {
    $record = new InspectionAttachmentRecord();
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
