<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;
use OAuth\Domain\ValueObject\ClientSecret;
use OAuth\Domain\ValueObject\GrantType;
use OAuth\Domain\ValueObject\GrantTypes;
use OAuth\Domain\ValueObject\RedirectUri;
use OAuth\Domain\ValueObject\Scope;
use OAuth\Domain\ValueObject\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ClientRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Tests\Helper\TestEventIdProvider;

use function password_hash;

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
            actual: $client
        );

        self::assertSame(
            expected: '123e4567-e89b-12d3-a456-426614174000',
            actual: $client->id()->value
        );

        self::assertSame(
            expected: 'Test Client',
            actual: $client->name()->value
        );

        self::assertSame(
            expected: '$2y$10$hashedsecret',
            actual: $client->secret()->value
        );

        self::assertSame(
            expected: ['https://example.com/callback'],
            actual: $client->redirectUris()
        );

        self::assertTrue(condition: $client->isActive());

        self::assertFalse(condition: $client->isDeleted());
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
        self::assertInstanceOf(expected: DateTimeImmutable::class, actual: $client->deletedAt());
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
        self::assertSame(expected: $originalClient->isActive(), actual: $mappedClient->isActive());
    }
    // #endregion
}
