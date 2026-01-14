<?php

declare(strict_types=1);

namespace Otp\Application\Service;

use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose, VerificationInfo};
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpHandler};
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpHandler};

/**
 * Service OtpChallengeService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpChallengeService implements OtpChallengePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param GenerateOtpHandler $generateHandler the OTP generation handler
   * @param VerifyOtpHandler $verifyHandler the OTP verification handler
   */
  public function __construct(
    private GenerateOtpHandler $generateHandler,
    private VerifyOtpHandler $verifyHandler,
  ) {
  }
  // #endregion

  // #region Methods
  public function generate(
    string $userId,
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $recipient,
    ?int $ttlSeconds = null,
    ?int $maxAttempts = null,
  ): ChallengeInfo {
    $command = new GenerateOtpCommand(
      userId: $userId,
      purpose: $purpose,
      channel: $channel,
      recipient: $recipient,
      ttlSeconds: $ttlSeconds,
      maxAttempts: $maxAttempts,
    );

    $result = $this->generateHandler->__invoke($command);

    return new ChallengeInfo(
      challengeToken: $result->token,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
    );
  }

  public function verify(string $challengeToken, string $code): VerificationInfo
  {
    $command = new VerifyOtpCommand(
      code: $code,
      challengeToken: $challengeToken,
    );

    try {
      $result = $this->verifyHandler->__invoke($command);
    } catch (OtpNotFoundException) {
      return new VerificationInfo(
        success: false,
        attemptsRemaining: 0,
        error: 'Challenge not found.',
      );
    }

    return new VerificationInfo(
      success: $result->success,
      attemptsRemaining: $result->attemptsRemaining,
      error: $result->error,
    );
  }
  // #endregion
}
