<?php

declare(strict_types=1);

namespace Otp\Domain\Model\Totp;

use DateTimeImmutable;
use Otp\Domain\ValueObject\TotpSecret;

/** Active and pending secret slots restored from enrollment storage. */
final readonly class TotpEnrollmentSecrets
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries active and pending TOTP enrollment secrets with their timestamps.
   *
   * @access public
   *
   * @param ?TotpSecret $activeSecret active secret, when enrollment is confirmed
   * @param ?DateTimeImmutable $activeConfirmedAt time when the active secret was confirmed
   * @param ?TotpSecret $pendingSecret pending secret awaiting confirmation
   * @param ?DateTimeImmutable $pendingCreatedAt time when the pending secret was created
   *
   * @return void
   */
  public function __construct(
    public ?TotpSecret $activeSecret,
    public ?DateTimeImmutable $activeConfirmedAt,
    public ?TotpSecret $pendingSecret,
    public ?DateTimeImmutable $pendingCreatedAt,
  ) {
  }
  // #endregion
}
