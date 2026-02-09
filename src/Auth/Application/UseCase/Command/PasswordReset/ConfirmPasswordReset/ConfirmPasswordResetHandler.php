<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\ValueObject\ChallengeToken;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\{HashedPassword, UserId};
use Otp\Domain\Exception\{OtpExpiredException, OtpMaxAttemptsException};

/**
 * Handler ConfirmPasswordResetHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordResetHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmPasswordResetHandler class.
   *
   * @since 1.0.0
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param SessionRepositoryPort $sessionRepository the session repository port
   * @param TokenRevocationPort $tokenRevocation the token revocation port
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
    private readonly UserRepositoryPort $userRepository,
    private readonly SessionRepositoryPort $sessionRepository,
    private readonly TokenRevocationPort $tokenRevocation,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ConfirmPasswordResetCommand.
   *
   * @since 1.0.0
   *
   * @param ConfirmPasswordResetCommand $command the command
   *
   * @return ConfirmPasswordResetResult the result
   */
  public function __invoke(ConfirmPasswordResetCommand $command): ConfirmPasswordResetResult
  {
    // Find the OTP challenge by token
    $challengeToken = ChallengeToken::fromString($command->token);
    $otp = $this->otpRepository->findByChallengeToken($challengeToken);

    if (null === $otp) {
      return ConfirmPasswordResetResult::failed(
        message: 'Invalid or expired reset token.',
        errorCode: ConfirmPasswordResetResult::ERROR_INVALID_TOKEN,
      );
    }

    // Verify the OTP code
    try {
      $verified = $otp->verify($command->code);

      // Persist updated state
      $this->otpRepository->save($otp);

      if (!$verified) {
        return ConfirmPasswordResetResult::failed(
          message: 'Invalid verification code. Please check and try again.',
          errorCode: ConfirmPasswordResetResult::ERROR_INVALID_CODE,
          attemptsRemaining: $otp->attemptsRemaining(),
        );
      }
    }
    catch (OtpExpiredException) {
      return ConfirmPasswordResetResult::failed(
        message: 'The password reset code has expired. Please request a new one.',
        errorCode: ConfirmPasswordResetResult::ERROR_EXPIRED,
      );
    }
    catch (OtpMaxAttemptsException) {
      return ConfirmPasswordResetResult::failed(
        message: 'Maximum verification attempts exceeded. Please request a new code.',
        errorCode: ConfirmPasswordResetResult::ERROR_MAX_ATTEMPTS,
      );
    }

    // Get the user from the OTP (OTP stores userId)
    $userId = new UserId($otp->userId());
    $user = $this->userRepository->findById($userId);

    if (null === $user) {
      return ConfirmPasswordResetResult::failed(
        message: 'User not found.',
        errorCode: ConfirmPasswordResetResult::ERROR_INVALID_TOKEN,
      );
    }

    // Change the password
    $user->changePassword(HashedPassword::fromPlain($command->newPassword));
    $this->userRepository->save($user);

    // Revoke all active sessions for security
    $this->sessionRepository->revokeAllForUser((string) $userId);

    // Revoke all OAuth tokens
    $this->tokenRevocation->revokeAllUserTokens((string) $userId);

    return ConfirmPasswordResetResult::success();
  }
  // #endregion
}
