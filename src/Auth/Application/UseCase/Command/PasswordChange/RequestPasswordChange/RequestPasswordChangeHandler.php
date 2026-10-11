<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange;

use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Inbound\UserCredentialsPort;

/**
 * Handler RequestPasswordChangeHandler.
 *
 * Verifies the authenticated user's current password, then issues
 * an OTP challenge (email) that must be confirmed before the
 * password is actually changed.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordChangeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RequestPasswordChangeHandler class.
   *
   * @since 1.0.0
   *
   * @param UserCredentialsPort $userRepository the user repository port
   * @param OtpChallengePort $otpChallenge the OTP challenge port
   */
  public function __construct(
    private readonly UserCredentialsPort $userRepository,
    private readonly OtpChallengePort $otpChallenge,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RequestPasswordChangeCommand.
   *
   * @since 1.0.0
   *
   * @param RequestPasswordChangeCommand $command the command
   *
   * @return RequestPasswordChangeResult the result
   */
  public function __invoke(RequestPasswordChangeCommand $command): RequestPasswordChangeResult
  {
    $authentication = $this->userRepository->authenticate($command->userId, $command->currentPassword);
    if ('authenticated' !== $authentication->status || null === $authentication->user) {
      return RequestPasswordChangeResult::failed(
        message: match ($authentication->status) {
          'invalid_password' => 'The current password is incorrect.',
          'unavailable' => 'This account cannot change its password in its current state.',
          default => 'User not found.',
        },
        errorCode: 'invalid_password' === $authentication->status
          ? RequestPasswordChangeResult::ERROR_INVALID_PASSWORD
          : RequestPasswordChangeResult::ERROR_USER_NOT_FOUND,
      );
    }
    $user = $authentication->user;

    $challenge = $this->otpChallenge->generate(
      userId: $user->id,
      purpose: OtpPurpose::SENSITIVE_OPERATION,
      channel: OtpChannel::EMAIL,
      recipient: $user->email,
    );

    return RequestPasswordChangeResult::success(
      challengeToken: $challenge->challengeToken,
      maskedRecipient: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
    );
  }
  // #endregion
}
