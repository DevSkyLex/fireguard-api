<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange;

use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\{InvalidPasswordException, InvalidUserException};
use User\Domain\ValueObject\UserId;

/**
 * Handler RequestPasswordChangeHandler.
 *
 * Verifies the authenticated user's current password, then issues
 * an OTP challenge (email) that must be confirmed before the
 * password is actually changed.
 *
 * @category Handler
 *
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
    $user = $this->userRepository->findById(new UserId($command->userId));

    if (null === $user) {
      return RequestPasswordChangeResult::failed(
        message: 'User not found.',
        errorCode: RequestPasswordChangeResult::ERROR_USER_NOT_FOUND,
      );
    }

    // Verify the current password before issuing an OTP challenge.
    // authenticate() tracks failed attempts and locks the account
    // past the threshold, giving brute-force protection for free.
    try {
      $user->authenticate($command->currentPassword);
      $this->userRepository->save($user);
    } catch (InvalidPasswordException) {
      $this->userRepository->save($user);

      return RequestPasswordChangeResult::failed(
        message: 'The current password is incorrect.',
        errorCode: RequestPasswordChangeResult::ERROR_INVALID_PASSWORD,
      );
    } catch (InvalidUserException) {
      return RequestPasswordChangeResult::failed(
        message: 'This account cannot change its password in its current state.',
        errorCode: RequestPasswordChangeResult::ERROR_USER_NOT_FOUND,
      );
    }

    $challenge = $this->otpChallenge->generate(
      userId: (string) $user->id(),
      purpose: OtpPurpose::SENSITIVE_OPERATION,
      channel: OtpChannel::EMAIL,
      recipient: $user->email()->value,
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
