<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Challenge;

use Otp\Application\Contract\Challenge\VerificationInfo;

/**
 * Port EmailOwnershipChallengePort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EmailOwnershipChallengePort
{
  // #region Methods
  /**
   * Consumes a challenge once under an auth-database lock, persisting failed attempts.
   *
   * @since 1.0.0
   *
   * @param string $userId the expected account
   * @param string $email the expected current address
   * @param string $token the challenge token
   * @param string $code the submitted code
   *
   * @return VerificationInfo neutral result without token or mailbox disclosure
   */
  public function verify(string $userId, string $email, string $token, string $code): VerificationInfo;
  // #endregion
}
