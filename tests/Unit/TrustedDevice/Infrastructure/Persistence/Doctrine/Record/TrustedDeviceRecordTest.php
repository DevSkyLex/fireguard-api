<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;

/**
 * Test TrustedDeviceRecordTest.
 *
 * @category Record Tests
 */
#[CoversClass(className: TrustedDeviceRecord::class)]
final class TrustedDeviceRecordTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testSettersAndGetters(): void
  {
    $record = new TrustedDeviceRecord();
    $lastUsedAt = new DateTimeImmutable('2024-01-02 00:00:00');
    $expiresAt = new DateTimeImmutable('2024-02-01 00:00:00');
    $createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    $record
      ->setId('123e4567-e89b-12d3-a456-426614174000')
      ->setUserId('user-123')
      ->setTokenHash('token-hash')
      ->setFingerprint('fingerprint')
      ->setUserAgent('Mozilla/5.0')
      ->setIpAddress('127.0.0.1')
      ->setName('Chrome on Windows')
      ->setLastUsedAt($lastUsedAt)
      ->setExpiresAt($expiresAt)
      ->setCreatedAt($createdAt)
      ->setRevoked(true);

    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $record->getId());
    self::assertSame('user-123', $record->getUserId());
    self::assertSame('token-hash', $record->getTokenHash());
    self::assertSame('fingerprint', $record->getFingerprint());
    self::assertSame('Mozilla/5.0', $record->getUserAgent());
    self::assertSame('127.0.0.1', $record->getIpAddress());
    self::assertSame('Chrome on Windows', $record->getName());
    self::assertSame($lastUsedAt, $record->getLastUsedAt());
    self::assertSame($expiresAt, $record->getExpiresAt());
    self::assertSame($createdAt, $record->getCreatedAt());
    self::assertTrue($record->isRevoked());
  }
  // #endregion
}
