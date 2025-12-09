<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Mfa;

use Auth\Application\UseCase\Command\MfaVerify\MfaVerifyResult;

/**
 * Interface ChallengeVerifierPort
 *
 * Defines the contract for verifying MFA challenges.
 *
 * @category Interface
 * @package Auth\Application\Port\Outbound\Mfa
 */
interface ChallengeVerifierPort
{
  /**
   * Verifies an OTP code for a given challenge token.
   *
   * @param string $challengeToken The challenge token.
   * @param string $code The verification code.
   *
   * @return MfaVerifyResult The verification result.
   */
  public function verify(string $challengeToken, string $code): MfaVerifyResult;
}
