<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Auth\Application\UseCase\Command\Mfa\MfaVerify\MfaVerifyResult;
use Otp\Application\Contract\Challenge\VerificationInfo;
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;

/**
 * Adapter OtpModuleChallengeVerifierAdapter.
 *
 * @category Adapter
 *
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
   * @param OtpChallengePort $challengePort the OTP challenge port
   */
  public function __construct(
    private OtpChallengePort $challengePort,
  ) {
  }
  // #endregion

  // #region Methods
  public function verify(string $challengeToken, string $code): MfaVerifyResult
  {
    /** @var VerificationInfo $result */
    $result = $this->challengePort->verify(
      challengeToken: $challengeToken,
      code: $code,
    );

    return new MfaVerifyResult(
      success: $result->success,
      attemptsRemaining: $result->attemptsRemaining,
      error: $result->error,
    );
  }
  // #endregion
}
