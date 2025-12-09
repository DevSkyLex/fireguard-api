<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Auth\Application\UseCase\Command\MfaVerify\MfaVerifyResult;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpCommand;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpHandler;

/**
 * Adapter OtpModuleChallengeVerifierAdapter
 * @final
 *
 * Implementation of ChallengeVerifierPort using the OTP module.
 *
 * @category Adapter
 * @package Auth\Infrastructure\Adapter\Mfa
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpModuleChallengeVerifierAdapter implements ChallengeVerifierPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * @param VerifyOtpHandler $handler The OTP verification handler.
   */
  public function __construct(
    private VerifyOtpHandler $handler,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function verify(string $challengeToken, string $code): MfaVerifyResult
  {
    $command = new VerifyOtpCommand(
      code: $code,
      challengeToken: $challengeToken,
    );

    $result = $this->handler->__invoke($command);

    return new MfaVerifyResult(
      success: $result->success,
      attemptsRemaining: $result->attemptsRemaining,
      error: $result->error,
    );
  }
  //#endregion
}
