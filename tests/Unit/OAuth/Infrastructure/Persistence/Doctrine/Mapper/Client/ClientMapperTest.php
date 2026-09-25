<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Persistence\Doctrine\Mapper\Client;

use DateTimeImmutable;
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use OAuth\Infrastructure\Persistence\Doctrine\Mapper\Client\ClientMapper;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Tests\Helper\TestEventIdProvider;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Test ClientMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientMapper::class)]
final class ClientMapperTest extends TestCase
{
  // #region Methods
  /**
   * Method testToDomainMapsRecordToClient.
   *
   * Test that toDomain correctly maps
   * a ClientRecord to a Client domain model
   *
   * @return void No return value
   */
  #[Test]
  public function testToDomainMapsRecordToClient(): void
  {
    $record = new ClientRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    $record->name = 'Test Client';
    $record->secret = '$2y$10$hashedsecret';
    $record->redirectUris = ['https://example.com/callback'];
    $record->grantTypes = ['AUTHORIZATION_CODE'];
    $record->scopes = ['READ'];
    $record->isActive = true;
    $record->createdAt = new DateTimeImmutable('2024-01-01 12:00:00');
    $record->deletedAt = null;

    $client = ClientMapper::toDomain(record: $record);

    self::assertInstanceOf(
      expected: Client::class,
      actual: $client,
    );

    self::assertSame(
      expected: '123e4567-e89b-12d3-a456-426614174000',
      actual: $client->id()->value,
    );

    self::assertSame(
      expected: 'Test Client',
      actual: $client->name()->value,
    );

    self::assertSame(
      expected: '$2y$10$hashedsecret',
      actual: $client->secret()->value,
    );

    self::assertSame(
      expected: ['https://example.com/callback'],
      actual: $client->redirectUris(),
    );

    self::assertSame(expected: $record->grantTypes, actual: $client->grantTypes()->toArray());
    self::assertSame(expected: $record->scopes, actual: $client->scopes()->toArray());
    self::assertTrue(condition: $client->isActive());
    self::assertFalse(condition: $client->isDeleted());
    self::assertSame(expected: $record->createdAt, actual: $client->createdAt());
    self::assertNull(actual: $client->deletedAt());
    self::assertFalse(condition: $client->hasRecordedEvents());
    self::assertFalse(condition: $client->validateRedirectUri(new RedirectUri(value: 'https://other.example/callback')));
    self::assertFalse(condition: $client->supportsGrantType(GrantType::REFRESH_TOKEN));
    self::assertFalse(condition: $client->hasScope(Scope::WRITE));
  }

  /**
   * Method testToDomainHandlesSoftDeletedClient.
   *
   * Test that toDomain correctly handles
   * a soft-deleted client
   *
   * @return void No return value
   */
  #[Test]
  public function testToDomainHandlesSoftDeletedClient(): void
  {
    $record = new ClientRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    $record->name = 'Deleted Client';
    $record->secret = '$2y$10$hashedsecret';
    $record->redirectUris = ['https://example.com/callback'];
    $record->grantTypes = ['AUTHORIZATION_CODE'];
    $record->scopes = ['READ'];
    $record->isActive = false;
    $record->createdAt = new DateTimeImmutable('2024-01-01 12:00:00');
    $record->deletedAt = new DateTimeImmutable('2024-01-02 12:00:00');

    $client = ClientMapper::toDomain(record: $record);

    self::assertTrue(condition: $client->isDeleted());
    self::assertFalse(condition: $client->isActive());
    self::assertSame(expected: $record->deletedAt, actual: $client->deletedAt());
    self::assertFalse(condition: $client->hasRecordedEvents());

    $client->delete(new TestEventIdProvider());
    self::assertFalse(condition: $client->hasRecordedEvents());

    $restoredRecord = ClientMapper::toRecord(client: $client);
    self::assertSame(expected: $record->isActive, actual: $restoredRecord->isActive);
    self::assertSame(expected: $record->createdAt, actual: $restoredRecord->createdAt);
    self::assertSame(expected: $record->deletedAt, actual: $restoredRecord->deletedAt);
  }

  /**
   * Method testToRecordMapsClientToRecord.
   *
   * Test that toRecord correctly maps
   * a Client domain model to a ClientRecord
   *
   * @return void No return value
   */
  #[Test]
  public function testToRecordMapsClientToRecord(): void
  {
    $hashedSecret = password_hash('test-secret', PASSWORD_BCRYPT);
    $client = Client::register(
      id: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName(value: 'Test Client'),
      secret: new ClientSecret(value: $hashedSecret),
      redirectUris: [new RedirectUri(value: 'https://example.com/callback')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: new TestEventIdProvider(),
    );

    $record = ClientMapper::toRecord(client: $client);

    self::assertInstanceOf(expected: ClientRecord::class, actual: $record);
    self::assertSame(expected: '123e4567-e89b-12d3-a456-426614174000', actual: $record->id->toRfc4122());
    self::assertSame(expected: 'Test Client', actual: $record->name);
    self::assertSame(expected: $hashedSecret, actual: $record->secret);
    self::assertSame(expected: ['https://example.com/callback'], actual: $record->redirectUris);
    self::assertSame(expected: ['AUTHORIZATION_CODE'], actual: $record->grantTypes);
    self::assertSame(expected: ['READ'], actual: $record->scopes);
    self::assertTrue(condition: $record->isActive);
    self::assertNull(actual: $record->deletedAt);
  }

  /**
   * Method testRoundTripMappingPreservesData.
   *
   * Test that mapping from domain to record
   * and back preserves all data
   *
   * @return void No return value
   */
  #[Test]
  public function testRoundTripMappingPreservesData(): void
  {
    $hashedSecret = password_hash('test-secret', PASSWORD_BCRYPT);
    $originalClient = Client::register(
      id: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName(value: 'Test Client'),
      secret: new ClientSecret(value: $hashedSecret),
      redirectUris: [new RedirectUri(value: 'https://example.com/callback')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: new TestEventIdProvider(),
    );

    // Domain -> Record -> Domain
    $record = ClientMapper::toRecord(client: $originalClient);
    $mappedClient = ClientMapper::toDomain(record: $record);

    self::assertSame(expected: $originalClient->id()->value, actual: $mappedClient->id()->value);
    self::assertSame(expected: $originalClient->name()->value, actual: $mappedClient->name()->value);
    self::assertSame(expected: $originalClient->secret()->value, actual: $mappedClient->secret()->value);
    self::assertSame(expected: $originalClient->redirectUris(), actual: $mappedClient->redirectUris());
    self::assertSame(expected: $originalClient->grantTypes()->toArray(), actual: $mappedClient->grantTypes()->toArray());
    self::assertSame(expected: $originalClient->scopes()->toArray(), actual: $mappedClient->scopes()->toArray());
    self::assertSame(expected: $originalClient->isActive(), actual: $mappedClient->isActive());
    self::assertSame(expected: $originalClient->createdAt(), actual: $mappedClient->createdAt());
    self::assertSame(expected: $originalClient->deletedAt(), actual: $mappedClient->deletedAt());
    self::assertFalse(condition: $mappedClient->hasRecordedEvents());
  }

  #[Test]
  public function testToOAuthClientMapsRecord(): void
  {
    $record = new ClientRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    $record->name = 'OAuth Client';
    $record->secret = 'secret';
    $record->redirectUris = ['https://example.com/callback'];
    $record->grantTypes = ['AUTHORIZATION_CODE', 'REFRESH_TOKEN'];
    $record->scopes = ['READ', 'WRITE'];
    $record->isActive = true;
    $record->createdAt = new DateTimeImmutable('2024-01-01 12:00:00');
    $record->deletedAt = null;

    $oauthClient = ClientMapper::toOAuthClient($record);

    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $oauthClient->identifier()->value);
    self::assertSame('OAuth Client', $oauthClient->name());
    self::assertSame(['https://example.com/callback'], $oauthClient->redirectUris());
    self::assertSame([GrantType::AUTHORIZATION_CODE, GrantType::REFRESH_TOKEN], $oauthClient->grantTypes());
    self::assertSame([Scope::READ, Scope::WRITE], $oauthClient->scopes());
    self::assertSame('secret', $oauthClient->secret());
    self::assertTrue($oauthClient->isConfidential());
  }
  // #endregion
}
