<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, TrustedDeviceId};
use TrustedDevice\Infrastructure\Persistence\Doctrine\Mapper\TrustedDeviceMapper;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;

/**
 * Test TrustedDeviceMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: TrustedDeviceMapper::class)]
final class TrustedDeviceMapperTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testToRecordMapsDomainFields(): void
  {
    $fingerprint = DeviceFingerprint::create('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '127.0.0.1');
    $device = TrustedDevice::trust(
      id: new TrustedDeviceId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      fingerprint: $fingerprint,
      ttlDays: 30,
    );
    $device->releaseEvents();

    $mapper = new TrustedDeviceMapper();
    $record = $mapper->toRecord($device);

    self::assertSame($device->id()->value, $record->getId());
    self::assertSame($device->userId(), $record->getUserId());
    self::assertSame($device->token()->hash, $record->getTokenHash());
    self::assertSame($device->fingerprint()->value, $record->getFingerprint());
    self::assertSame($device->fingerprint()->userAgent, $record->getUserAgent());
    self::assertSame($device->fingerprint()->ipAddress, $record->getIpAddress());
    self::assertSame($device->name(), $record->getName());
    self::assertSame($device->lastUsedAt(), $record->getLastUsedAt());
    self::assertSame($device->expiresAt(), $record->getExpiresAt());
    self::assertSame($device->createdAt(), $record->getCreatedAt());
    self::assertSame($device->isRevoked(), $record->isRevoked());
  }

  #[Test]
  public function testToDomainMapsRecordFields(): void
  {
    $record = new TrustedDeviceRecord();
    $record
      ->setId('123e4567-e89b-12d3-a456-426614174001')
      ->setUserId('user-456')
      ->setTokenHash('token-hash')
      ->setFingerprint('fingerprint-hash')
      ->setUserAgent('Mozilla/5.0 (Macintosh)')
      ->setIpAddress('127.0.0.2')
      ->setName('Safari on macOS')
      ->setLastUsedAt(new DateTimeImmutable('2024-01-02 00:00:00'))
      ->setExpiresAt(new DateTimeImmutable('2024-02-01 00:00:00'))
      ->setCreatedAt(new DateTimeImmutable('2024-01-01 00:00:00'))
      ->setRevoked(true);

    $mapper = new TrustedDeviceMapper();
    $device = $mapper->toDomain($record);

    self::assertSame('123e4567-e89b-12d3-a456-426614174001', $device->id()->value);
    self::assertSame('user-456', $device->userId());
    self::assertSame('token-hash', $device->token()->hash);
    self::assertSame('fingerprint-hash', $device->fingerprint()->value);
    self::assertSame('Mozilla/5.0 (Macintosh)', $device->fingerprint()->userAgent);
    self::assertSame('127.0.0.2', $device->fingerprint()->ipAddress);
    self::assertSame('Safari on macOS', $device->name());
    self::assertTrue($device->isRevoked());
  }
  // #endregion
}
