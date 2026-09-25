<?php

declare(strict_types=1);

namespace Otp\Domain\Model\Totp;

use DateTimeImmutable;

/** Independent confirmation and disable attempt counters. */
final readonly class TotpEnrollmentAttempts
{
  public function __construct(
    public int $attempts,
    public int $maxAttempts,
    public int $disableAttempts = 0,
    public ?DateTimeImmutable $disableLockedUntil = null,
  ) {
  }
}
