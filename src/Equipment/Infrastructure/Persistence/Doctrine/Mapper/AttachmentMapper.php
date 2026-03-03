<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentAttachmentRecord;

/**
 * Mapper AttachmentMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AttachmentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   */
  public static function toDomain(EquipmentAttachmentRecord $record): EquipmentAttachment
  {
    return EquipmentAttachment::reconstitute(
      id: AttachmentId::fromString($record->id),
      equipmentId: EquipmentId::fromString($record->equipmentId),
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
  public static function toRecord(EquipmentAttachment $attachment): EquipmentAttachmentRecord
  {
    $record = new EquipmentAttachmentRecord();
    $record->id = (string) $attachment->id();
    $record->equipmentId = (string) $attachment->equipmentId();
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
