<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Challenge;

use Otp\Application\Contract\Challenge\{OtpPurpose, VerificationInfo};

/**
 * Interface MfaChallengeVerificationPort
 *
 * Verifies a login proof bound to the signed pre-authentication account and challenge.
 * Recovery proof and challenge consumption commit together; rejected attempts remain durable.
 *
 * @category Port
 */
interface MfaChallengeVerificationPort
{
  // #region Methods
  /**
   * Method verifyFor
   *
   * @access public
   *
   * @param string $challengeToken signed pre-authentication challenge token
   * @param string $userId signed account identifier
   * @param OtpPurpose $purpose expected LOGIN purpose
   * @param string $method otp or recovery_code
   * @param string $proof submitted code
   *
   * @return VerificationInfo committed verification result
   */
  public function verifyFor(string $challengeToken, string $userId, OtpPurpose $purpose, string $method, string $proof): VerificationInfo;
  // #endregion
}
