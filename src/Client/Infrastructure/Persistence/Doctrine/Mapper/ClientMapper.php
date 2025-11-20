<?php

declare(strict_types=1);

namespace Client\Infrastructure\Persistence\Doctrine\Mapper;

use Client\Domain\Model\Client;
use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientName;
use Client\Domain\ValueObject\ClientSecret;
use Client\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use ReflectionClass;
use Shared\Domain\ValueObject\GrantTypes;
use Shared\Domain\ValueObject\Scopes;
use Symfony\Component\Uid\Uuid;

/**
 * Mapper ClientMapper
 * @final
 *
 * Maps between Client domain model 
 * and ClientRecord.
 *
 * @category Mapper
 * @package Client\Infrastructure\Persistence\Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientMapper
{
  //#region Methods
  /**
   * Method toDomain
   * @static
   *
   * Maps a ClientRecord to a Client domain model.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRecord $record The record to map.
   *
   * @return Client The domain model.
   */
  public static function toDomain(ClientRecord $record): Client
  {
    $reflection = new ReflectionClass(objectOrClass: Client::class);
    $client = $reflection->newInstanceWithoutConstructor();

    $idProperty = $reflection->getProperty(name: 'id');
    $idProperty->setValue($client, new ClientId(value: $record->id->toRfc4122()));

    $nameProperty = $reflection->getProperty(name: 'name');
    $nameProperty->setValue($client, new ClientName(value: $record->name));

    $secretProperty = $reflection->getProperty(name: 'secret');
    $secretProperty->setValue($client, new ClientSecret(value: $record->secret));

    $redirectUrisProperty = $reflection->getProperty(name: 'redirectUris');
    $redirectUrisProperty->setValue($client, $record->redirectUris);

    $grantTypesProperty = $reflection->getProperty(name: 'grantTypes');
    $grantTypesProperty->setValue($client, GrantTypes::fromArray(grantTypes: $record->grantTypes));

    $scopesProperty = $reflection->getProperty(name: 'scopes');
    $scopesProperty->setValue($client, Scopes::fromArray(scopes: $record->scopes));

    $isActiveProperty = $reflection->getProperty(name: 'isActive');
    $isActiveProperty->setValue($client, $record->isActive);

    $createdAtProperty = $reflection->getProperty(name: 'createdAt');
    $createdAtProperty->setValue($client, $record->createdAt);

    $deletedAtProperty = $reflection->getProperty(name: 'deletedAt');
    $deletedAtProperty->setValue($client, $record->deletedAt);

    return $client;
  }

  /**
   * Method toRecord
   * @static
   *
   * Maps a Client domain model to a ClientRecord.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Client $client The domain model to map.
   *
   * @return ClientRecord The record.
   */
  public static function toRecord(Client $client): ClientRecord
  {
    $record = new ClientRecord();

    $record->id = Uuid::fromString(uuid: $client->id()->value);
    $record->name = $client->name()->value;
    $record->secret = $client->secret()->value;
    $record->redirectUris = $client->redirectUris();
    $record->grantTypes = $client->grantTypes()->toArray();
    $record->scopes = $client->scopes()->toArray();
    $record->isActive = $client->isActive();
    $record->createdAt = $client->createdAt();
    $record->deletedAt = $client->deletedAt();

    return $record;
  }
  //#endregion
}
