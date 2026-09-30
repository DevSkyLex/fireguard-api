<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Persistence\Doctrine\Mapper;

use TrustedDevice\Domain\Model\TrustedDevice\TrustedDevice;
use TrustedDevice\Domain\ValueObject\{DeviceFingerprint, TrustedDeviceId, TrustedDeviceTimeline};
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;

/**
 * Mapper TrustedDeviceMapper.
 */
final readonly class TrustedDeviceMapper
{
  /**
   * Method toRecord
   *
   * Maps trusted device state into its Doctrine record, reusing a supplied record when present.
   *
   * @access public
   *
   * @param TrustedDevice $device the trusted device aggregate
   * @param TrustedDeviceRecord|null $record the record to update, when supplied
   *
   * @return TrustedDeviceRecord the mapped persistence record
   */
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

  /**
   * Method toDomain
   *
   * Reconstitutes a trusted device aggregate from its persistence record.
   *
   * @access public
   *
   * @param TrustedDeviceRecord $record the persistence record
   *
   * @return TrustedDevice the reconstructed aggregate
   */
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
      timeline: new TrustedDeviceTimeline($record->getLastUsedAt(), $record->getExpiresAt(), $record->getCreatedAt()),
      revoked: $record->isRevoked(),
    );
  }
}
