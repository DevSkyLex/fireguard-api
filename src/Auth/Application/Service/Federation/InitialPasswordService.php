<?php

declare(strict_types=1);

namespace Auth\Application\Service\Federation;

use Auth\Domain\Exception\Federation\{FederatedAuthException, FederatedConflictException, FederatedUnauthorizedException};
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use User\Application\Port\Inbound\FederatedUserPort;

/**
 * Service InitialPasswordService.
 *
 * Adds an OTP-protected local password to an account that was provisioned by
 * a federated identity provider.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InitialPasswordService
{
  public function __construct(
    private FederatedUserPort $users,
    private OtpChallengePort $otpChallenge,
  ) {
  }

  /**
   * Creates an email challenge for the authenticated federated-only user.
   *
   * @since 1.0.0
   *
   * @throws FederatedAuthException when the account is unavailable or already has a password
   */
  public function request(string $userId): ChallengeInfo
  {
    $user = $this->users->find($userId);
    if (null === $user) {
      throw new FederatedUnauthorizedException('account_unavailable', 'This account is unavailable.');
    }
    if ($user->passwordConfigured) {
      throw new FederatedConflictException('password_already_configured', 'A password is already configured.');
    }

    return $this->otpChallenge->generate(
      userId: $userId,
      purpose: OtpPurpose::SENSITIVE_OPERATION,
      channel: OtpChannel::EMAIL,
      recipient: $user->email,
    );
  }

  /**
   * Verifies the one-time code and configures the first local password.
   *
   * @since 1.0.0
   *
   * @throws FederatedAuthException when the challenge or password transition is invalid
   *
   * @return array{attemptsRemaining: int}
   */
  public function confirm(string $userId, string $token, string $code, string $password): array
  {
    $verification = $this->otpChallenge->verifyFor(
      challengeToken: $token,
      code: $code,
      userId: $userId,
      purpose: OtpPurpose::SENSITIVE_OPERATION,
    );
    if (!$verification->success) {
      $errorCode = $verification->errorCode ?? 'invalid_code';
      $message = match ($errorCode) {
        'expired' => 'The verification code expired.',
        'max_attempts_exceeded' => 'Too many attempts.',
        'invalid_token' => 'The verification request is invalid.',
        default => (string) $verification->attemptsRemaining,
      };

      throw new FederatedAuthException($errorCode, $message);
    }

    if (!$this->users->setInitialPassword($userId, $password)) {
      throw new FederatedConflictException('password_already_configured', 'A password is already configured.');
    }

    return ['attemptsRemaining' => $verification->attemptsRemaining];
  }
}
