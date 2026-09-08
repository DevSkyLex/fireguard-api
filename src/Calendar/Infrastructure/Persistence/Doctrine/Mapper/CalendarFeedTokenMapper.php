<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Persistence\Doctrine\Mapper;

use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarFeedTokenRecord;

/**
 * Mapper CalendarFeedTokenMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenRecord $record the persistence record
   *
   * @return CalendarFeedToken the domain aggregate
   */
  public static function toDomain(CalendarFeedTokenRecord $record): CalendarFeedToken
  {
    return CalendarFeedToken::reconstitute(
      id: CalendarFeedTokenId::fromString($record->id),
      organizationId: $record->organizationId,
      userId: $record->userId,
      tokenHash: $record->tokenHash,
      createdAt: $record->createdAt,
      lastUsedAt: $record->lastUsedAt,
      revokedAt: $record->revokedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param CalendarFeedToken $token the domain aggregate
   * @param CalendarFeedTokenRecord $record the persistence record to populate
   */
  public static function toRecord(CalendarFeedToken $token, CalendarFeedTokenRecord $record): void
  {
    $record->id = (string) $token->id();
    $record->organizationId = $token->organizationId();
    $record->userId = $token->userId();
    $record->tokenHash = $token->tokenHash();
    $record->createdAt = $token->createdAt();
    $record->lastUsedAt = $token->lastUsedAt();
    $record->revokedAt = $token->revokedAt();
  }
  // #endregion
}
