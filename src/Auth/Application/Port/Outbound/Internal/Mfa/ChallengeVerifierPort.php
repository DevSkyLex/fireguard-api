<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Internal\Mfa;

use Auth\Application\UseCase\Command\Mfa\MfaVerify\MfaVerifyResult;

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
   * @param string $code the verification code
   * @param string $userId account identifier from the verified pre-auth token
   * @param string $method otp or recovery_code
   *
   * @return MfaVerifyResult the verification result
   */
  public function verify(string $challengeToken, string $code, string $userId, string $method = 'otp'): MfaVerifyResult;
}
