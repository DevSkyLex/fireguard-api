<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Domain\ValueObject\ChallengeToken;
use Shared\Application\Message\CommandHandler;

/**
 * Handler ResendPasswordResetHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendPasswordResetHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ResendPasswordResetHandler class.
   *
   * @since 1.0.0
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository port
   * @param OtpChallengePort $otpChallenge the OTP challenge port
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
    private readonly OtpChallengePort $otpChallenge,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ResendPasswordResetCommand.
   *
   * @since 1.0.0
   *
   * @param ResendPasswordResetCommand $command the command
   *
   * @return ResendPasswordResetResult the result
   */
  public function __invoke(ResendPasswordResetCommand $command): ResendPasswordResetResult
  {
    $challengeToken = ChallengeToken::fromString($command->token);
    $otp = $this->otpRepository->findByChallengeToken($challengeToken);

    if (null === $otp || OtpPurpose::PASSWORD_RESET->value !== $otp->purpose()->value || 'pending' !== $otp->status()) {
      return ResendPasswordResetResult::failed(
        message: 'Invalid or expired reset token.',
        errorCode: ResendPasswordResetResult::ERROR_INVALID_TOKEN,
      );
    }

    $now = new DateTimeImmutable();
    $canResendIn = ChallengeResendPolicy::canResendIn(
      createdAt: $otp->createdAt(),
      now: $now,
    );

    if ($canResendIn > 0) {
      return ResendPasswordResetResult::failed(
        message: "Please wait {$canResendIn} seconds before resending.",
        errorCode: ResendPasswordResetResult::ERROR_RESEND_NOT_ALLOWED,
        retryAfter: $canResendIn,
      );
    }

    $challenge = $this->otpChallenge->generate(
      userId: $otp->userId(),
      purpose: OtpPurpose::PASSWORD_RESET,
      channel: OtpChannel::from($otp->channel()->value),
      recipient: $otp->recipient(),
    );

    return ResendPasswordResetResult::success(
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
      canResendIn: ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS,
      message: 'A new password reset code has been sent.',
    );
  }
  // #endregion
}
