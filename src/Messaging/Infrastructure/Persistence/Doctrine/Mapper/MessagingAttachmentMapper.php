<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Mapper;

use LogicException;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingAttachmentRecord, MessagingMessageRecord};

/**
 * Mapper MessagingAttachmentMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingAttachmentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   */
  public static function toDomain(MessagingAttachmentRecord $record): MessagingAttachment
  {
    if (!$record->message instanceof MessagingMessageRecord) {
      throw new LogicException('Attachment record must reference a message.');
    }

    return MessagingAttachment::reconstitute(
      id: MessagingAttachmentId::fromString($record->id),
      messageId: $record->message->id,
      conversationId: $record->conversationId,
      organizationId: $record->organizationId,
      uploadedByMemberId: $record->uploadedByMemberId,
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
  public static function toRecord(MessagingAttachment $attachment): MessagingAttachmentRecord
  {
    $record = new MessagingAttachmentRecord();
    $record->id = (string) $attachment->id();
    $record->conversationId = $attachment->conversationId();
    $record->organizationId = $attachment->organizationId();
    $record->uploadedByMemberId = $attachment->uploadedByMemberId();
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
