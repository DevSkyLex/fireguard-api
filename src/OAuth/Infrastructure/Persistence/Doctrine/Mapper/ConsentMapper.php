<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Mapper;

use OAuth\Domain\Model\Consent\Consent;
use OAuth\Domain\ValueObject\Consent\ConsentId;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ConsentRecord;
use Symfony\Component\Uid\Uuid;

/**
 * Mapper ConsentMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConsentMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * Maps a ConsentRecord to a Consent domain model.
   *
   * @since 1.0.0
   *
   * @param ConsentRecord $record the record to map
   *
   * @return Consent the domain model
   */
  public static function toDomain(ConsentRecord $record): Consent
  {
    return Consent::restore(
      id: new ConsentId(value: $record->id->toRfc4122()),
      userId: $record->userId,
      clientId: $record->clientId,
      scopes: Scopes::fromArray(scopes: $record->scopes),
      grantedAt: $record->grantedAt,
      revokedAt: $record->revokedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * Maps a Consent domain model to a ConsentRecord.
   *
   * @since 1.0.0
   *
   * @param Consent $consent the domain model to map
   *
   * @return ConsentRecord the record
   */
  public static function toRecord(Consent $consent): ConsentRecord
  {
    $record = new ConsentRecord();

    $record->id = Uuid::fromString(uuid: (string) $consent->id());
    $record->userId = $consent->userId();
    $record->clientId = $consent->clientId();
    $record->scopes = $consent->scopes()->toArray();
    $record->grantedAt = $consent->grantedAt();
    $record->revokedAt = $consent->revokedAt();

    return $record;
  }
  // #endregion
}
