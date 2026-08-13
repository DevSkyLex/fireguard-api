<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\{InterventionAttachmentId, InterventionAttachmentKind};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionAttachmentRecord, InterventionRecord};
use LogicException;

/**
 * Mapper InterventionAttachmentMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAttachmentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   */
  public static function toDomain(InterventionAttachmentRecord $record): InterventionAttachment
  {
    if (!$record->intervention instanceof InterventionRecord) {
      throw new LogicException('Attachment record must reference an intervention.');
    }

    return InterventionAttachment::reconstitute(
      id: InterventionAttachmentId::fromString($record->id),
      interventionId: $record->intervention->id,
      fileName: $record->fileName,
      storagePath: $record->storagePath,
      mimeType: $record->mimeType,
      size: $record->size,
      uploadedAt: $record->uploadedAt,
      label: $record->label,
      workItemId: $record->workItem?->id,
      kind: InterventionAttachmentKind::tryFrom($record->kind) ?? InterventionAttachmentKind::FILE,
    );
  }

  /**
   * Method toRecord.
   *
   * @since 1.0.0
   */
  public static function toRecord(InterventionAttachment $attachment): InterventionAttachmentRecord
  {
    $record = new InterventionAttachmentRecord();
    $record->id = (string) $attachment->id();
    $record->fileName = $attachment->fileName();
    $record->storagePath = $attachment->storagePath();
    $record->mimeType = $attachment->mimeType();
    $record->size = $attachment->size();
    $record->label = $attachment->label();
    $record->uploadedAt = $attachment->uploadedAt();
    $record->kind = $attachment->kind()->value;

    return $record;
  }
  // #endregion
}
