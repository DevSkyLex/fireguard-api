<?php

declare(strict_types=1);

namespace Otp\Domain\Model;

use DateTimeImmutable;

/** Persisted verifier and attempt timeline of an OTP challenge. */
final readonly class OtpRestoredProgress
{
  public function __construct(
    public string $codeHash,
    public DateTimeImmutable $expiresAt,
    public int $maxAttempts,
    public int $attempts,
    public ?DateTimeImmutable $verifiedAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
}
