<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use DateTimeImmutable;

/**
 * Item TrustedDeviceItemResult.
 */
final readonly class TrustedDeviceItemResult
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
