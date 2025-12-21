<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Mapper;

use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;
use OAuth\Domain\ValueObject\ClientSecret;
use OAuth\Domain\ValueObject\GrantTypes;
use OAuth\Domain\ValueObject\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use ReflectionClass;
use Symfony\Component\Uid\Uuid;

use function array_map;
use function array_values;

/**
 * Mapper ClientMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * Maps a ClientRecord to a Client domain model.
   *
   * @since 1.0.0
   *
   * @param ClientRecord $record the record to map
   *
   * @return Client the domain model
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
   * Method toRecord.
   *
   * @static
   *
   * Maps a Client domain model to
   * a ClientRecord.
   *
   * @since 1.0.0
   *
   * @param Client $client the domain model to map
   *
   * @return ClientRecord the record
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

  /**
   * Method toLeague.
   *
   * @static
   *
   * Maps a ClientRecord to a LeagueClient
   * domain model.
   *
   * @since 1.0.0
   *
   * @param ClientRecord $record the record to map
   *
   * @return \OAuth\Domain\Model\LeagueClient the domain model
   */
  public static function toLeague(ClientRecord $record): \OAuth\Domain\Model\LeagueClient
  {
    return new \OAuth\Domain\Model\LeagueClient(
      identifier: new \OAuth\Domain\ValueObject\OAuthClientIdentifier($record->id->toRfc4122()),
      name: $record->name,
      redirectUris: array_values($record->redirectUris),
      grantTypes: array_values(array_map(
        fn (string $grantType) => \OAuth\Domain\ValueObject\GrantType::from($grantType),
        $record->grantTypes,
      )),
      scopes: array_values(array_map(
        fn (string $scope) => \OAuth\Domain\ValueObject\Scope::from($scope),
        $record->scopes,
      )),
      secret: $record->secret,
      isConfidential: true, // Assuming all valid clients in DB are confidential for now
    );
  }
  // #endregion
}
