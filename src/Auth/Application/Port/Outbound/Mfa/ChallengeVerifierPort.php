<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Mfa;

use Otp\Presentation\Api\Dto\VerifyOtpInput;
use Otp\Presentation\Api\Dto\VerifyOtpOutput;

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
   * @param string $token The challenge token.
   * @param VerifyOtpInput $input The verification input (code).
   *
   * @return VerifyOtpOutput The verification result.
   */
  public function verify(string $token, VerifyOtpInput $input): VerifyOtpOutput;
}
