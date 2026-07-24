<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageRole, AssistantMessageStatus};
use Assistant\Infrastructure\Persistence\Doctrine\Record\{AssistantMessageRecord, AssistantThreadRecord};
use RuntimeException;

/**
 * Mapper AssistantMessageMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantMessageMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantMessageRecord $record the persistence record
   *
   * @return AssistantMessage the domain aggregate
   */
  public static function toDomain(AssistantMessageRecord $record): AssistantMessage
  {
    if (!$record->thread instanceof AssistantThreadRecord) {
      throw new RuntimeException('An assistant message record must be associated with a thread.');
    }

    return AssistantMessage::reconstitute(
      id: AssistantMessageId::fromString($record->id),
      threadId: $record->thread->id,
      organizationId: $record->organizationId,
      role: AssistantMessageRole::from($record->role),
      body: $record->body,
      status: AssistantMessageStatus::from($record->status),
      errorCode: $record->errorCode,
      tokenCount: $record->tokenCount,
      createdAt: $record->createdAt,
      completedAt: $record->completedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantMessage $message the domain aggregate
   * @param AssistantMessageRecord $record the persistence record to populate
   */
  public static function toRecord(AssistantMessage $message, AssistantMessageRecord $record): void
  {
    $record->id = (string) $message->id();
    $record->organizationId = $message->organizationId();
    $record->role = $message->role()->value;
    $record->body = $message->body();
    $record->status = $message->status()->value;
    $record->errorCode = $message->errorCode();
    $record->tokenCount = $message->tokenCount();
    $record->createdAt = $message->createdAt();
    $record->completedAt = $message->completedAt();
  }
  // #endregion
}
