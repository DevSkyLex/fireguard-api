<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\Port\Outbound\Mfa\ChallengeVerifierPort;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpCommand;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpHandler;
use Otp\Presentation\Api\Dto\VerifyOtpInput;
use Otp\Presentation\Api\Dto\VerifyOtpOutput;

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
  public function verify(string $token, VerifyOtpInput $input): VerifyOtpOutput
  {
    $command = new VerifyOtpCommand(
      code: $input->code,
      challengeToken: $token,
    );

    $result = $this->handler->__invoke($command);

    $output = new VerifyOtpOutput();
    $output->success = $result->success;
    $output->attemptsRemaining = $result->attemptsRemaining;
    $output->error = $result->error;

    return $output;
  }
  //#endregion
}
