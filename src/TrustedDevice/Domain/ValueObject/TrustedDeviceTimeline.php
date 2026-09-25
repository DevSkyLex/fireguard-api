<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\ValueObject;

use DateTimeImmutable;

/** Persisted lifecycle timestamps of a trusted device. */
final readonly class TrustedDeviceTimeline
{
  public function __construct(
    public DateTimeImmutable $lastUsedAt,
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
}
