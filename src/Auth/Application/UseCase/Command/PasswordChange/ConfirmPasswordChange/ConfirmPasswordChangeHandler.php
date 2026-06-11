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
 * Handler ConfirmPasswordChangeHandler.
 *
 * Verifies the OTP challenge issued by RequestPasswordChange,
 * ensures it belongs to the authenticated user, then changes
 * the password and revokes sessions and OAuth tokens.
 *
 * @category Handler
 *
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
    try {
      $verified = $otp->verify($command->code);

      // Persist updated state
      $this->otpRepository->save($otp);

      if (!$verified) {
        return ConfirmPasswordChangeResult::failed(
          message: 'Invalid verification code. Please check and try again.',
          errorCode: ConfirmPasswordChangeResult::ERROR_INVALID_CODE,
          attemptsRemaining: $otp->attemptsRemaining(),
        );
      }
    } catch (OtpExpiredException) {
      return ConfirmPasswordChangeResult::failed(
        message: 'The verification code has expired. Please request a new one.',
        errorCode: ConfirmPasswordChangeResult::ERROR_EXPIRED,
      );
    } catch (OtpMaxAttemptsException) {
      return ConfirmPasswordChangeResult::failed(
        message: 'Maximum verification attempts exceeded. Please request a new code.',
        errorCode: ConfirmPasswordChangeResult::ERROR_MAX_ATTEMPTS,
      );
    }

    $userId = new UserId($command->userId);
    $user = $this->userRepository->findById($userId);

    if (null === $user) {
      return ConfirmPasswordChangeResult::failed(
        message: 'User not found.',
        errorCode: ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    // Change the password
    $user->changePassword(HashedPassword::fromPlain($command->newPassword));
    $this->userRepository->save($user);

    // Revoke all active sessions and OAuth tokens for security:
    // any session hijacker loses access once the password changes.
    $this->sessionRepository->revokeAllForUser((string) $userId);
    $this->tokenRevocation->revokeAllUserTokens((string) $userId);

    return ConfirmPasswordChangeResult::success();
  }
  // #endregion
}
