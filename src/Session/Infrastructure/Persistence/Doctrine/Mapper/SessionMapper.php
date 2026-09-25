<?php

declare(strict_types=1);

namespace Session\Infrastructure\Persistence\Doctrine\Mapper;

use Session\Domain\Model\Session\{RestoredSessionLifecycle, RestoredSessionTokens, Session};
use Session\Domain\ValueObject\{SessionId, SessionMetadata};
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};
use Symfony\Component\Uid\Uuid;

/**
 * Mapper SessionMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * Maps a SessionRecord to a Session domain model.
   *
   * @since 1.0.0
   *
   * @param SessionRecord $record the record to map
   *
   * @return Session the domain model
   */
  public static function toDomain(SessionRecord $record): Session
  {
    return Session::restore(
      id: new SessionId(value: $record->id->toRfc4122()),
      userId: $record->userId,
      ipAddress: new IpAddress(value: $record->ipAddress),
      userAgent: new UserAgent(value: $record->userAgent),
      metadata: SessionMetadata::fromArray(data: $record->metadata),
      tokens: new RestoredSessionTokens(
        accessTokenId: $record->accessTokenId,
        refreshTokenId: $record->refreshTokenId,
      ),
      lifecycle: new RestoredSessionLifecycle(
        createdAt: $record->createdAt,
        lastActivityAt: $record->lastActivityAt,
        revokedAt: $record->revokedAt,
      ),
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * Maps a Session domain model to a SessionRecord.
   *
   * @since 1.0.0
   *
   * @param Session $session the domain model to map
   *
   * @return SessionRecord the record
   */
  public static function toRecord(Session $session): SessionRecord
  {
    $record = new SessionRecord();

    $record->id = Uuid::fromString(uuid: (string) $session->id());
    $record->userId = $session->userId();
    $record->accessTokenId = $session->accessTokenId();
    $record->refreshTokenId = $session->refreshTokenId();
    $record->ipAddress = (string) $session->ipAddress();
    $record->userAgent = (string) $session->userAgent();
    $record->metadata = $session->metadata()->toArray();
    $record->createdAt = $session->createdAt();
    $record->lastActivityAt = $session->lastActivityAt();
    $record->revokedAt = $session->revokedAt();

    return $record;
  }
  // #endregion
}
