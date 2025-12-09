<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\ListTrustedDevices;

use DateTimeImmutable;

/**
 * Result ListTrustedDevicesResult
 * @final
 */
final readonly class ListTrustedDevicesResult
{
  /**
   * @param list<TrustedDeviceItem> $devices
   */
  public function __construct(
    public array $devices,
  ) {
  }
}

/**
 * Item TrustedDeviceItem
 * @final
 */
final readonly class TrustedDeviceItem
{
  public function __construct(
    public string $id,
    public string $name,
    public DateTimeImmutable $lastUsedAt,
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
    public bool $isCurrentDevice = false,
  ) {
  }
}
