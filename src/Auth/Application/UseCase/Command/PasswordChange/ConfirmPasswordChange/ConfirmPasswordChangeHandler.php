<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Otp\Application\Contract\Challenge\OtpPurpose;
use Otp\Application\Port\Inbound\Challenge\{ChallengeAccessPort, OtpChallengePort};
use Session\Application\Port\Inbound\SessionRevocationPort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Inbound\UserCredentialsPort;
use User\Application\Port\Outbound\PasswordPolicyPort;

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
    $this->passwordPolicy->assertAcceptable($command->newPassword);

    $otp = $this->otpRepository->find($command->token);
    if (null === $otp || $otp->userId !== $command->userId || OtpPurpose::SENSITIVE_OPERATION !== $otp->purpose) {
      return ConfirmPasswordChangeResult::failed(
        message: 'Invalid or expired change token.',
        errorCode: ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN,
      );
    }
    $verification = $this->otpChallenge->verifyFor(
      challengeToken: $command->token,
      code: $command->code,
      userId: $otp->userId,
      purpose: OtpPurpose::SENSITIVE_OPERATION,
    );
    if (!$verification->success) {
      return ConfirmPasswordChangeResult::failed(
        message: match ($verification->errorCode) {
          'expired' => 'The verification code has expired. Please request a new one.',
          'max_attempts_exceeded' => 'Maximum verification attempts exceeded. Please request a new code.',
          'invalid_token' => 'Invalid or expired change token.',
          default => 'Invalid verification code. Please check and try again.',
        },
        errorCode: match ($verification->errorCode) {
          'expired' => ConfirmPasswordChangeResult::ERROR_EXPIRED,
          'max_attempts_exceeded' => ConfirmPasswordChangeResult::ERROR_MAX_ATTEMPTS,
          'invalid_token' => ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN,
          default => ConfirmPasswordChangeResult::ERROR_INVALID_CODE,
        },
        attemptsRemaining: $verification->attemptsRemaining,
      );
    }

    return $this->changePassword($otp->userId, $command->newPassword);
  }

  /**
   * Method changePassword.
   *
   * Saves the new password, revokes active sessions and requests OAuth token revocation.
   *
   * @access private
   *
   * @param string $userId the account identifier
   * @param string $newPassword the new plain password to hash and store
   *
   * @return ConfirmPasswordChangeResult the password change outcome
   */
  private function changePassword(string $userId, string $newPassword): ConfirmPasswordChangeResult
  {
    if (!$this->userRepository->changePassword($userId, $newPassword)) {
      return ConfirmPasswordChangeResult::failed(
        message: 'User not found.',
        errorCode: ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN,
      );
    }

    // Revoke active sessions and request OAuth token revocation after the password changes.
    $this->sessionRepository->revokeAllForUser((string) $userId);
    $this->tokenRevocation->revokeAllUserTokens((string) $userId);

    return ConfirmPasswordChangeResult::success();
  }
  // #endregion
}
