<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Infrastructure\Persistence\Doctrine\Mapper\SessionMapper;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};
use Symfony\Component\Uid\Uuid;

/**
 * Test SessionMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: SessionMapper::class)]
final class SessionMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToRecordMapsSession(): void
  {
    $session = Session::create(
      id: new SessionId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-1',
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('agent'),
      accessTokenId: 'access-1',
      refreshTokenId: 'refresh-1',
    );

    $record = SessionMapper::toRecord($session);

    self::assertSame('user-1', $record->userId);
    self::assertSame('access-1', $record->accessTokenId);
    self::assertSame('refresh-1', $record->refreshTokenId);
    self::assertSame('127.0.0.1', $record->ipAddress);
  }

  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $record = $this->sessionRecord();

    $session = SessionMapper::toDomain($record);

    self::assertInstanceOf(Session::class, $session);
    self::assertSame($record->id->toRfc4122(), (string) $session->id());
    self::assertSame('user-1', $session->userId());
    self::assertSame('access-1', $session->accessTokenId());
    self::assertSame('refresh-1', $session->refreshTokenId());
    self::assertSame('127.0.0.1', (string) $session->ipAddress());
    self::assertSame('agent', (string) $session->userAgent());
    self::assertSame($record->metadata, $session->metadata()->toArray());
    self::assertSame($record->createdAt, $session->createdAt());
    self::assertSame($record->lastActivityAt, $session->lastActivityAt());
    self::assertFalse($session->isRevoked());
    self::assertNull($session->revokedAt());

    $restored = SessionMapper::toRecord($session);
    self::assertEquals($record, $restored);
  }

  #[Test]
  public function testToDomainPreservesRevocationTimestamp(): void
  {
    $revokedAt = new DateTimeImmutable('2024-01-03 00:00:00');
    $record = $this->sessionRecord($revokedAt);

    $session = SessionMapper::toDomain($record);

    self::assertTrue($session->isRevoked());
    self::assertSame($revokedAt, $session->revokedAt());
    $session->revoke();
    self::assertSame($revokedAt, $session->revokedAt());
    self::assertEquals($record, SessionMapper::toRecord($session));
  }

  private function sessionRecord(?DateTimeImmutable $revokedAt = null): SessionRecord
  {
    $record = new SessionRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    $record->userId = 'user-1';
    $record->accessTokenId = 'access-1';
    $record->refreshTokenId = 'refresh-1';
    $record->ipAddress = '127.0.0.1';
    $record->userAgent = 'agent';
    $record->metadata = [
      'device_type' => 'desktop',
      'browser' => 'Firefox',
      'operating_system' => 'Windows',
      'country' => 'FR',
      'city' => 'Paris',
      'remember_me' => true,
    ];
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->lastActivityAt = new DateTimeImmutable('2024-01-02 00:00:00');
    $record->revokedAt = $revokedAt;

    return $record;
  }
  // #endregion
}
