<?php

declare(strict_types=1);

namespace Otp\Domain\Model\Totp;

use DateTimeImmutable;

/** Independent confirmation and disable attempt counters. */
final readonly class TotpEnrollmentAttempts
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries TOTP confirmation and disable attempt limits and lock state.
   *
   * @access public
   *
   * @param int $attempts number of enrollment confirmation attempts
   * @param int $maxAttempts maximum allowed confirmation attempts
   * @param int $disableAttempts number of attempts to disable TOTP
   * @param ?DateTimeImmutable $disableLockedUntil optional end time of a disable lock
   *
   * @return void
   */
  public function __construct(
    public int $attempts,
    public int $maxAttempts,
    public int $disableAttempts = 0,
    public ?DateTimeImmutable $disableLockedUntil = null,
  ) {
  }
  // #endregion
}
