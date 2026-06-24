<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\ResendRegistration;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Domain\ValueObject\ChallengeToken;
use Shared\Application\Message\CommandHandler;

/**
 * Handler ResendRegistrationHandler.
 *
 * Resends the email-verification OTP for a pending registration. Mirrors the
 * password-reset resend handler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendRegistrationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ResendRegistrationHandler class.
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
   * Handles the ResendRegistrationCommand.
   *
   * @since 1.0.0
   *
   * @param ResendRegistrationCommand $command the command
   *
   * @return ResendRegistrationResult the result
   */
  public function __invoke(ResendRegistrationCommand $command): ResendRegistrationResult
  {
    $challengeToken = ChallengeToken::fromString($command->token);
    $otp = $this->otpRepository->findByChallengeToken($challengeToken);

    if (null === $otp || OtpPurpose::EMAIL_VERIFICATION->value !== $otp->purpose()->value || 'pending' !== $otp->status()) {
      return ResendRegistrationResult::failed(
        message: 'Invalid or expired verification token.',
        errorCode: ResendRegistrationResult::ERROR_INVALID_TOKEN,
      );
    }

    $now = new DateTimeImmutable();
    $canResendIn = ChallengeResendPolicy::canResendIn(
      createdAt: $otp->createdAt(),
      now: $now,
    );

    if ($canResendIn > 0) {
      return ResendRegistrationResult::failed(
        message: "Please wait {$canResendIn} seconds before resending.",
        errorCode: ResendRegistrationResult::ERROR_RESEND_NOT_ALLOWED,
        retryAfter: $canResendIn,
      );
    }

    $challenge = $this->otpChallenge->generate(
      userId: $otp->userId(),
      purpose: OtpPurpose::EMAIL_VERIFICATION,
      channel: OtpChannel::from($otp->channel()->value),
      recipient: $otp->recipient(),
    );

    return ResendRegistrationResult::success(
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
      canResendIn: ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS,
      message: 'A new verification code has been sent.',
    );
  }
  // #endregion
}
