<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Mfa;

use Auth\Application\UseCase\Command\MfaVerify\MfaVerifyResult;

/**
 * Interface ChallengeVerifierPort.
 *
 * Defines the contract for verifying MFA challenges.
 *
 * @category Interface
 */
interface ChallengeVerifierPort
{
  /**
   * Verifies an OTP code for a given challenge token.
   *
   * @param string $challengeToken the challenge token
   * @param string $code           the verification code
   *
   * @return MfaVerifyResult the verification result
   */
  public function verify(string $challengeToken, string $code): MfaVerifyResult;
}
