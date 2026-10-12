<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\Port\Outbound\Internal\Mfa\ChallengeVerifierPort;
use Auth\Application\UseCase\Command\Mfa\MfaVerify\MfaVerifyResult;
use Otp\Application\Contract\Challenge\{OtpPurpose, VerificationInfo};
use Otp\Application\Port\Inbound\Challenge\MfaChallengeVerificationPort;

/**
 * Adapter OtpModuleChallengeVerifierAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpModuleChallengeVerifierAdapter implements ChallengeVerifierPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param MfaChallengeVerificationPort $challengePort the bound MFA verification capability
   */
  public function __construct(
    private MfaChallengeVerificationPort $challengePort,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method verify.
   *
   * Delegates MFA challenge verification to the OTP module and maps its result.
   *
   * @access public
   *
   * @param string $challengeToken the pre-authentication challenge token
   * @param string $code the submitted one-time code
   * @param string $userId account identifier from the signed pre-auth token
   * @param string $method otp or recovery_code
   *
   * @return MfaVerifyResult the mapped verification result
   */
  public function verify(string $challengeToken, string $code, string $userId, string $method = 'otp'): MfaVerifyResult
  {
    /** @var VerificationInfo $result */
    $result = $this->challengePort->verifyFor(
      challengeToken: $challengeToken,
      userId: $userId,
      purpose: OtpPurpose::LOGIN,
      method: $method,
      proof: $code,
    );

    return new MfaVerifyResult(
      success: $result->success,
      attemptsRemaining: $result->attemptsRemaining,
      error: $result->error,
    );
  }
  // #endregion
}
