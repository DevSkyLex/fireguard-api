<?php

declare(strict_types=1);

namespace Otp\Domain\Model\Totp;

use DateTimeImmutable;
use Otp\Domain\ValueObject\TotpSecret;

/** Active and pending secret slots restored from enrollment storage. */
final readonly class TotpEnrollmentSecrets
{
  public function __construct(
    public ?TotpSecret $activeSecret,
    public ?DateTimeImmutable $activeConfirmedAt,
    public ?TotpSecret $pendingSecret,
    public ?DateTimeImmutable $pendingCreatedAt,
  ) {
  }
}
