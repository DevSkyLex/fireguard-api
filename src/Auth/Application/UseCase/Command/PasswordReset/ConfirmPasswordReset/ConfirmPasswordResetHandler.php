<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Otp\Application\Contract\Challenge\OtpPurpose;
use Otp\Application\Port\Inbound\Challenge\{ChallengeAccessPort, OtpChallengePort};
use Session\Application\Port\Inbound\SessionRevocationPort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Inbound\UserCredentialsPort;
use User\Application\Port\Outbound\PasswordPolicyPort;

/**
 * Handler ConfirmPasswordResetHandler.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordResetHandler implements CommandHandler
{
  // #region Properties
  /**
   * Constant INVALID_RESET_MESSAGE.
   *
   * Neutral refusal shared by invalid reset tokens and codes.
   */
  private const string INVALID_RESET_MESSAGE = 'Invalid or expired reset code. Please check the code, or request a new reset.';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmPasswordResetHandler class.
   *
   * @since 1.0.0
   *
   * @param ChallengeAccessPort $otpRepository the OTP repository port
   * @param UserCredentialsPort $userRepository the user repository port
   * @param SessionRevocationPort $sessionRepository the session repository port
   * @param TokenRevocationPort $tokenRevocation the token revocation port
   * @param PasswordPolicyPort $passwordPolicy authoritative requirements checked before consuming the OTP
   */
  public function __construct(
    private readonly ChallengeAccessPort $otpRepository,
    private readonly UserCredentialsPort $userRepository,
    private readonly SessionRevocationPort $sessionRepository,
    private readonly TokenRevocationPort $tokenRevocation,
    private OtpChallengePort $otpChallenge,
    private PasswordPolicyPort $passwordPolicy,
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
    $this->passwordPolicy->assertAcceptable($command->newPassword);

    $otp = $this->otpRepository->find($command->token);
    if (null === $otp || OtpPurpose::PASSWORD_RESET !== $otp->purpose) {
      return ConfirmPasswordResetResult::failed(
        message: self::INVALID_RESET_MESSAGE,
        errorCode: ConfirmPasswordResetResult::ERROR_INVALID_TOKEN,
      );
    }
    $verification = $this->otpChallenge->verifyFor(
      challengeToken: $command->token,
      code: $command->code,
      userId: $otp->userId,
      purpose: OtpPurpose::PASSWORD_RESET,
    );
    if (!$verification->success) {
      return ConfirmPasswordResetResult::failed(
        message: match ($verification->errorCode) {
          'expired' => 'The password reset code has expired. Please request a new one.',
          'max_attempts_exceeded' => 'Maximum verification attempts exceeded. Please request a new code.',
          default => self::INVALID_RESET_MESSAGE,
        },
        errorCode: match ($verification->errorCode) {
          'expired' => ConfirmPasswordResetResult::ERROR_EXPIRED,
          'max_attempts_exceeded' => ConfirmPasswordResetResult::ERROR_MAX_ATTEMPTS,
          'invalid_token' => ConfirmPasswordResetResult::ERROR_INVALID_TOKEN,
          default => ConfirmPasswordResetResult::ERROR_INVALID_CODE,
        },
        attemptsRemaining: $verification->attemptsRemaining,
      );
    }

    return $this->changePassword($otp->userId, $command->newPassword);
  }

  /**
   * Method changePassword.
   *
   * Saves the reset password, revokes active sessions and requests OAuth token revocation.
   *
   * @access private
   *
   * @param string $userId the account identifier
   * @param string $newPassword the new plain password to hash and store
   *
   * @return ConfirmPasswordResetResult the password reset outcome
   */
  private function changePassword(string $userId, string $newPassword): ConfirmPasswordResetResult
  {
    if (!$this->userRepository->changePassword($userId, $newPassword)) {
      return ConfirmPasswordResetResult::failed(
        message: 'User not found.',
        errorCode: ConfirmPasswordResetResult::ERROR_INVALID_TOKEN,
      );
    }

    // Revoke all active sessions for security
    $this->sessionRepository->revokeAllForUser((string) $userId);

    // Request revocation of all OAuth tokens.
    $this->tokenRevocation->revokeAllUserTokens((string) $userId);

    return ConfirmPasswordResetResult::success();
  }
  // #endregion
}
