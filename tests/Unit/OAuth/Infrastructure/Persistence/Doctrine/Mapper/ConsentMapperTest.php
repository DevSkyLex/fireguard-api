<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use OAuth\Domain\Model\Consent\Consent;
use OAuth\Domain\ValueObject\Consent\ConsentId;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Mapper\ConsentMapper;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ConsentRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Test ConsentMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: ConsentMapper::class)]
final class ConsentMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $record = new ConsentRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    $record->userId = 'user-1';
    $record->clientId = 'client-1';
    $record->scopes = ['OPENID'];
    $record->grantedAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->revokedAt = null;

    $consent = ConsentMapper::toDomain($record);

    self::assertSame($record->id->toRfc4122(), $consent->id()->value);
    self::assertSame('user-1', $consent->userId());
    self::assertSame('client-1', $consent->clientId());
    self::assertSame(['OPENID'], $consent->scopes()->toArray());
    self::assertSame($record->grantedAt, $consent->grantedAt());
    self::assertFalse($consent->isRevoked());
    self::assertNull($consent->revokedAt());
    self::assertFalse($consent->hasScope('READ'));

    $restoredRecord = ConsentMapper::toRecord($consent);
    self::assertSame($record->id->toRfc4122(), $restoredRecord->id->toRfc4122());
    self::assertSame($record->grantedAt, $restoredRecord->grantedAt);
    self::assertNull($restoredRecord->revokedAt);
  }

  #[Test]
  public function testToDomainPreservesRevokedConsent(): void
  {
    $record = new ConsentRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    $record->userId = 'user-1';
    $record->clientId = 'client-1';
    $record->scopes = ['OPENID'];
    $record->grantedAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->revokedAt = new DateTimeImmutable('2024-01-02 00:00:00');

    $consent = ConsentMapper::toDomain($record);

    self::assertTrue($consent->isRevoked());
    self::assertSame($record->revokedAt, $consent->revokedAt());
    $consent->revoke();
    self::assertSame($record->revokedAt, $consent->revokedAt());

    $restoredRecord = ConsentMapper::toRecord($consent);
    self::assertSame($record->scopes, $restoredRecord->scopes);
    self::assertSame($record->grantedAt, $restoredRecord->grantedAt);
    self::assertSame($record->revokedAt, $restoredRecord->revokedAt);
  }

  #[Test]
  public function testToRecordMapsConsent(): void
  {
    $consent = Consent::grant(
      id: new ConsentId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-1',
      clientId: 'client-1',
      scopes: Scopes::fromArray(['OPENID']),
    );

    $record = ConsentMapper::toRecord($consent);

    self::assertSame('user-1', $record->userId);
    self::assertSame('client-1', $record->clientId);
    self::assertSame(['OPENID'], $record->scopes);
  }
  // #endregion
}
