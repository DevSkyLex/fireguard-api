<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Mfa;

use DateTimeImmutable;

/** The replacement challenge and delivery details returned after a resend. */
final readonly class ResentMfaChallenge
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the replacement MFA challenge and the delivery and retry limits needed by the client.
   *
   * @access public
   *
   * @param string $preAuthToken token binding the pending authentication to this challenge
   * @param string $challengeToken token identifying the replacement OTP challenge
   * @param string $mfaMethod delivery or authenticator method selected for MFA
   * @param string $mfaDestination masked destination associated with the selected method
   * @param DateTimeImmutable $expiresAt deadline of the replacement challenge
   * @param int $maxAttempts maximum verification attempts allowed for this challenge
   * @param int $canResendIn remaining cooldown in seconds before another resend
   *
   * @return void
   */
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
  // #endregion
}
