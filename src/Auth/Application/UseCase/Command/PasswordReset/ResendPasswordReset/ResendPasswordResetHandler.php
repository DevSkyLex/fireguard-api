<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\{ChallengeAccessPort, OtpChallengePort};
use Shared\Application\Message\CommandHandler;

/**
 * Handler ResendPasswordResetHandler.
 *
 * @category Handler
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
   * @param ChallengeAccessPort $otpRepository the OTP repository port
   * @param OtpChallengePort $otpChallenge the OTP challenge port
   */
  public function __construct(
    private readonly ChallengeAccessPort $otpRepository,
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
    $otp = $this->otpRepository->find($command->token);

    if (null === $otp || OtpPurpose::PASSWORD_RESET->value !== $otp->purpose->value || 'pending' !== $otp->status) {
      return ResendPasswordResetResult::failed(
        // Mirrors the confirm step: a decoy token must fail the same way a real
        // one does, or resending would reveal which addresses have accounts.
        message: 'Invalid or expired reset code. Please check the code, or request a new reset.',
        errorCode: ResendPasswordResetResult::ERROR_INVALID_TOKEN,
      );
    }

    $now = new DateTimeImmutable();
    $canResendIn = $this->otpRepository->canResendIn(
      createdAt: $otp->createdAt,
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
      userId: $otp->userId,
      purpose: OtpPurpose::PASSWORD_RESET,
      channel: OtpChannel::from($otp->channel->value),
      recipient: $otp->recipient,
    );

    return ResendPasswordResetResult::success(
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
      canResendIn: ChallengeAccessPort::RESEND_COOLDOWN_SECONDS,
      message: 'A new password reset code has been sent.',
    );
  }
  // #endregion
}
