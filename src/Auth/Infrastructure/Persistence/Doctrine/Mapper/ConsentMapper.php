<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Mapper;

use Auth\Domain\Model\Consent;
use Auth\Domain\ValueObject\ConsentId;
use Auth\Infrastructure\Persistence\Doctrine\Record\ConsentRecord;
use ReflectionClass;
use Shared\Domain\ValueObject\Scopes;
use Symfony\Component\Uid\Uuid;

/**
 * Mapper ConsentMapper
 * @final
 *
 * Maps between Consent domain model and ConsentRecord.
 *
 * @category Mapper
 * @package Auth\Infrastructure\Persistence\Doctrine\Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConsentMapper
{
  //#region Methods
  /**
   * Method toDomain
   * @static
   *
   * Maps a ConsentRecord to a Consent domain model.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ConsentRecord $record The record to map.
   *
   * @return Consent The domain model.
   */
  public static function toDomain(ConsentRecord $record): Consent
  {
    $reflection = new ReflectionClass(objectOrClass: Consent::class);
    $consent = $reflection->newInstanceWithoutConstructor();

    $idProperty = $reflection->getProperty(name: 'id');
    $idProperty->setValue($consent, new ConsentId(value: $record->id->toRfc4122()));

    $userIdProperty = $reflection->getProperty(name: 'userId');
    $userIdProperty->setValue($consent, $record->userId);

    $clientIdProperty = $reflection->getProperty(name: 'clientId');
    $clientIdProperty->setValue($consent, $record->clientId);

    $scopesProperty = $reflection->getProperty(name: 'scopes');
    $scopesProperty->setValue($consent, Scopes::fromArray(scopes: $record->scopes));

    $grantedAtProperty = $reflection->getProperty(name: 'grantedAt');
    $grantedAtProperty->setValue($consent, $record->grantedAt);

    $revokedAtProperty = $reflection->getProperty(name: 'revokedAt');
    $revokedAtProperty->setValue($consent, $record->revokedAt);

    return $consent;
  }

  /**
   * Method toRecord
   * @static
   *
   * Maps a Consent domain model to a ConsentRecord.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Consent $consent The domain model to map.
   *
   * @return ConsentRecord The record.
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
  //#endregion
}
