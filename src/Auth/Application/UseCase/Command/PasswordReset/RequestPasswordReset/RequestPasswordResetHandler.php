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

    // No account, or one that cannot sign in: answer with a decoy challenge.
    //
    // Returning a bare success was already the intent, but it produced a *shorter*
    // payload than the real one — no `challengeToken`, no `maskedRecipient` — and
    // that difference enumerated the entire user base one request at a time. The
    // decoy is issued but never stored, so whatever code the caller submits next
    // fails through the ordinary invalid-challenge path.
    if (null === $user || !$user->canLogin()) {
      $decoy = $this->otpChallenge->generateDecoy(
        purpose: OtpPurpose::PASSWORD_RESET,
        channel: OtpChannel::EMAIL,
        recipient: $email->value,
      );

      return RequestPasswordResetResult::success(
        challengeToken: $decoy->challengeToken,
        maskedRecipient: $decoy->maskedRecipient,
        expiresAt: $decoy->expiresAt,
        maxAttempts: $decoy->maxAttempts,
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
