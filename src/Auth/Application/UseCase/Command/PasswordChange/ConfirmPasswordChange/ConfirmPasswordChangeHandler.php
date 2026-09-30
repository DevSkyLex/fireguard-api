<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Otp\Application\Contract\Challenge\OtpPurpose;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Exception\{OtpExpiredException, OtpMaxAttemptsException};
use Otp\Domain\ValueObject\ChallengeToken;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\{HashedPassword, UserId};

/**
 * Class ConfirmPasswordChangeHandler
 *
 * Verifies the authenticated user's password-change challenge, updates the password, revokes sessions and requests
 * OAuth token revocation.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordChangeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmPasswordChangeHandler class.
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
   * Handles the ConfirmPasswordChangeCommand.
   *
   * @since 1.0.0
   *
   * @param ConfirmPasswordChangeCommand $command the command
   *
   * @return ConfirmPasswordChangeResult the result
   */
  public function __invoke(ConfirmPasswordChangeCommand $command): ConfirmPasswordChangeResult
  {
    // Find the OTP challenge by token
    $challengeToken = ChallengeToken::fromString($command->token);
    $otp = $this->otpRepository->findByChallengeToken($challengeToken);

    // The challenge must exist, target the authenticated user, and
    // have been issued for a sensitive operation — otherwise treat it
    // as an invalid token without leaking which check failed.
    if (
      null === $otp
      || $otp->userId() !== $command->userId
      || OtpPurpose::SENSITIVE_OPERATION->value !== $otp->purpose()->value
    ) {
      return ConfirmPasswordChangeResult::failed(
        message: 'Invalid or expired change token.',
        errorCode: ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    // Verify the OTP code
    $verificationFailure = null;

    try {
      $verified = $otp->verify($command->code);

      // Persist updated state
      $this->otpRepository->save($otp);

      if (!$verified) {
        $verificationFailure = ConfirmPasswordChangeResult::failed(
          message: 'Invalid verification code. Please check and try again.',
          errorCode: ConfirmPasswordChangeResult::ERROR_INVALID_CODE,
          attemptsRemaining: $otp->attemptsRemaining(),
        );
      }
    } catch (OtpExpiredException) {
      $verificationFailure = ConfirmPasswordChangeResult::failed(
        message: 'The verification code has expired. Please request a new one.',
        errorCode: ConfirmPasswordChangeResult::ERROR_EXPIRED,
      );
    } catch (OtpMaxAttemptsException) {
      $verificationFailure = ConfirmPasswordChangeResult::failed(
        message: 'Maximum verification attempts exceeded. Please request a new code.',
        errorCode: ConfirmPasswordChangeResult::ERROR_MAX_ATTEMPTS,
      );
    }

    if (null !== $verificationFailure) {
      return $verificationFailure;
    }

    return $this->changePassword(new UserId($command->userId), $command->newPassword);
  }

  /**
   * Method changePassword.
   *
   * Saves the new password, revokes active sessions and requests OAuth token revocation.
   *
   * @access private
   *
   * @param UserId $userId the account identifier
   * @param string $newPassword the new plain password to hash and store
   *
   * @return ConfirmPasswordChangeResult the password change outcome
   */
  private function changePassword(UserId $userId, string $newPassword): ConfirmPasswordChangeResult
  {
    $user = $this->userRepository->findById($userId);

    if (null === $user) {
      return ConfirmPasswordChangeResult::failed(
        message: 'User not found.',
        errorCode: ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    // Change the password
    $user->changePassword(HashedPassword::fromPlain($newPassword));
    $this->userRepository->save($user);

    // Revoke active sessions and request OAuth token revocation after the password changes.
    $this->sessionRepository->revokeAllForUser((string) $userId);
    $this->tokenRevocation->revokeAllUserTokens((string) $userId);

    return ConfirmPasswordChangeResult::success();
  }
  // #endregion
}
