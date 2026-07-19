<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use Assistant\Infrastructure\Persistence\Doctrine\Record\AssistantThreadRecord;

/**
 * Mapper AssistantThreadMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantThreadMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantThreadRecord $record the persistence record
   *
   * @return AssistantThread the domain aggregate
   */
  public static function toDomain(AssistantThreadRecord $record): AssistantThread
  {
    return AssistantThread::reconstitute(
      id: AssistantThreadId::fromString($record->id),
      organizationId: $record->organizationId,
      memberId: $record->memberId,
      title: $record->title,
      model: $record->model,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      lastMessageAt: $record->lastMessageAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantThread $thread the domain aggregate
   * @param AssistantThreadRecord $record the persistence record to populate
   */
  public static function toRecord(AssistantThread $thread, AssistantThreadRecord $record): void
  {
    $record->id = (string) $thread->id();
    $record->organizationId = $thread->organizationId();
    $record->memberId = $thread->memberId();
    $record->title = $thread->title();
    $record->model = $thread->model();
    $record->createdAt = $thread->createdAt();
    $record->updatedAt = $thread->updatedAt();
    $record->lastMessageAt = $thread->lastMessageAt();
  }
  // #endregion
}
