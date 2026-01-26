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
    $record = new SessionRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    $record->userId = 'user-1';
    $record->accessTokenId = 'access-1';
    $record->refreshTokenId = 'refresh-1';
    $record->ipAddress = '127.0.0.1';
    $record->userAgent = 'agent';
    $record->metadata = ['key' => 'value'];
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->lastActivityAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->revokedAt = null;

    $session = SessionMapper::toDomain($record);

    self::assertInstanceOf(Session::class, $session);
    self::assertSame('user-1', $session->userId());
    self::assertSame('access-1', $session->accessTokenId());
  }
  // #endregion
}
