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

    self::assertSame('user-1', $consent->userId());
    self::assertSame('client-1', $consent->clientId());
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
