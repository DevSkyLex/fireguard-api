<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset;

use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Service\ChallengeResendPolicy;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;

use function strtolower;

/**
 * Handler RequestPasswordResetHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordResetHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RequestPasswordResetHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository port
   * @param OtpChallengePort $otpChallenge the OTP challenge port
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
    private readonly OtpChallengePort $otpChallenge,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RequestPasswordResetCommand.
   *
   * @since 1.0.0
   *
   * @param RequestPasswordResetCommand $command the command
   *
   * @return RequestPasswordResetResult the result
   */
  public function __invoke(RequestPasswordResetCommand $command): RequestPasswordResetResult
  {
    $email = new Email(strtolower($command->email));

    // Find user by email (timing-safe: always return success to prevent enumeration)
    $user = $this->userRepository->findByEmail($email);

    if (null === $user) {
      // Silent success to prevent user enumeration
      return RequestPasswordResetResult::success(
        canResendIn: ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS,
      );
    }

    // Only process if user can login (is active/verified)
    if (!$user->canLogin()) {
      // Silent success to prevent user enumeration
      return RequestPasswordResetResult::success(
        canResendIn: ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS,
      );
    }

    // Generate OTP challenge
    $challenge = $this->otpChallenge->generate(
      userId: (string) $user->id(),
      purpose: OtpPurpose::PASSWORD_RESET,
      channel: OtpChannel::EMAIL,
      recipient: $user->email()->value,
    );

    return RequestPasswordResetResult::success(
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
      canResendIn: ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS,
    );
  }
  // #endregion
}
