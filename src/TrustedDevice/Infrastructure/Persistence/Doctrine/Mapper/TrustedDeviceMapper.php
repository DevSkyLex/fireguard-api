<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Persistence\Doctrine\Mapper;

use TrustedDevice\Domain\Model\TrustedDevice;
use TrustedDevice\Domain\ValueObject\DeviceFingerprint;
use TrustedDevice\Domain\ValueObject\TrustedDeviceId;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;

/**
 * Mapper TrustedDeviceMapper.
 */
final readonly class TrustedDeviceMapper
{
  public function toRecord(TrustedDevice $device, ?TrustedDeviceRecord $record = null): TrustedDeviceRecord
  {
    $record = $record ?? new TrustedDeviceRecord();

    return $record
      ->setId($device->id()->value)
      ->setUserId($device->userId())
      ->setTokenHash($device->token()->hash)
      ->setFingerprint($device->fingerprint()->value)
      ->setUserAgent($device->fingerprint()->userAgent)
      ->setIpAddress($device->fingerprint()->ipAddress)
      ->setName($device->name())
      ->setLastUsedAt($device->lastUsedAt())
      ->setExpiresAt($device->expiresAt())
      ->setCreatedAt($device->createdAt())
      ->setRevoked($device->isRevoked());
  }

  public function toDomain(TrustedDeviceRecord $record): TrustedDevice
  {
    $fingerprint = DeviceFingerprint::fromHash(
      hash: $record->getFingerprint(),
      userAgent: $record->getUserAgent(),
      ipAddress: $record->getIpAddress(),
    );

    return TrustedDevice::reconstitute(
      id: new TrustedDeviceId($record->getId()),
      userId: $record->getUserId(),
      tokenHash: $record->getTokenHash(),
      fingerprint: $fingerprint,
      name: $record->getName(),
      lastUsedAt: $record->getLastUsedAt(),
      expiresAt: $record->getExpiresAt(),
      createdAt: $record->getCreatedAt(),
      revoked: $record->isRevoked(),
    );
  }
}
