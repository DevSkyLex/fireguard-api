<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaResend;

use DateTimeImmutable;

/** The replacement challenge and delivery details returned after a resend. */
final readonly class ResentMfaChallenge
{
  public function __construct(
    public string $preAuthToken,
    public string $challengeToken,
    public string $mfaMethod,
    public string $mfaDestination,
    public DateTimeImmutable $expiresAt,
    public int $maxAttempts,
    public int $canResendIn,
  ) {
  }
}
