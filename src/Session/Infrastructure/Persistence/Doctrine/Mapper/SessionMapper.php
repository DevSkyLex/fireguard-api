<?php

declare(strict_types=1);

namespace Session\Infrastructure\Persistence\Doctrine\Mapper;

use ReflectionClass;
use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Domain\ValueObject\SessionMetadata;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;
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
    $reflection = new ReflectionClass(objectOrClass: Session::class);
    $session = $reflection->newInstanceWithoutConstructor();

    $idProperty = $reflection->getProperty(name: 'id');
    $idProperty->setValue($session, new SessionId(value: $record->id->toRfc4122()));

    $userIdProperty = $reflection->getProperty(name: 'userId');
    $userIdProperty->setValue($session, $record->userId);

    $accessTokenIdProperty = $reflection->getProperty(name: 'accessTokenId');
    $accessTokenIdProperty->setValue($session, $record->accessTokenId);

    $refreshTokenIdProperty = $reflection->getProperty(name: 'refreshTokenId');
    $refreshTokenIdProperty->setValue($session, $record->refreshTokenId);

    $ipAddressProperty = $reflection->getProperty(name: 'ipAddress');
    $ipAddressProperty->setValue($session, new IpAddress(value: $record->ipAddress));

    $userAgentProperty = $reflection->getProperty(name: 'userAgent');
    $userAgentProperty->setValue($session, new UserAgent(value: $record->userAgent));

    $metadataProperty = $reflection->getProperty(name: 'metadata');
    $metadataProperty->setValue($session, SessionMetadata::fromArray(data: $record->metadata));

    $createdAtProperty = $reflection->getProperty(name: 'createdAt');
    $createdAtProperty->setValue($session, $record->createdAt);

    $lastActivityAtProperty = $reflection->getProperty(name: 'lastActivityAt');
    $lastActivityAtProperty->setValue($session, $record->lastActivityAt);

    $revokedAtProperty = $reflection->getProperty(name: 'revokedAt');
    $revokedAtProperty->setValue($session, $record->revokedAt);

    return $session;
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
