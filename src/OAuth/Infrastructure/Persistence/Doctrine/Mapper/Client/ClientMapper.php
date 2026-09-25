<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Mapper\Client;

use OAuth\Domain\Model\Client\{Client, RestoredClientLifecycle, RestoredClientSettings};
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret};
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Domain\ValueObject\Security\GrantTypes;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
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
    /** @var list<string> $redirectUris */
    $redirectUris = $record->redirectUris;

    return Client::restore(
      id: new ClientId(value: $record->id->toRfc4122()),
      name: new ClientName(value: $record->name),
      secret: new ClientSecret(value: $record->secret),
      settings: new RestoredClientSettings(
        redirectUris: $redirectUris,
        grantTypes: GrantTypes::fromArray(grantTypes: $record->grantTypes),
        scopes: Scopes::fromArray(scopes: $record->scopes),
      ),
      lifecycle: new RestoredClientLifecycle(
        isActive: $record->isActive,
        createdAt: $record->createdAt,
        deletedAt: $record->deletedAt,
      ),
    );
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
   * Method toOAuthClient.
   *
   * @static
   *
   * Maps a ClientRecord to an OAuthClient
   * domain model.
   *
   * @since 1.0.0
   *
   * @param ClientRecord $record the record to map
   *
   * @return \OAuth\Domain\Model\Client\OAuthClient the domain model
   */
  public static function toOAuthClient(ClientRecord $record): \OAuth\Domain\Model\Client\OAuthClient
  {
    return new \OAuth\Domain\Model\Client\OAuthClient(
      identifier: new \OAuth\Domain\ValueObject\Client\OAuthClientIdentifier($record->id->toRfc4122()),
      name: $record->name,
      redirectUris: array_values($record->redirectUris),
      grantTypes: array_values(array_map(
        fn (string $grantType) => \OAuth\Domain\ValueObject\Security\GrantType::from($grantType),
        $record->grantTypes,
      )),
      scopes: array_values(array_map(
        fn (string $scope) => \OAuth\Domain\ValueObject\Scope\Scope::from($scope),
        $record->scopes,
      )),
      secret: $record->secret,
      isConfidential: true, // Assuming all valid clients in DB are confidential for now
    );
  }
  // #endregion
}
