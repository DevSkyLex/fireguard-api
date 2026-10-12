<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\VerifyMfaChallenge;

use Auth\Application\Port\Outbound\AuthenticationPolicyPort;
use InvalidArgumentException;
use Otp\Application\Contract\Challenge\{OtpPurpose as ExpectedPurpose, VerificationInfo};
use Otp\Application\Contract\Event\Totp\RecoveryCodeConsumedEvent;
use Otp\Application\Port\Outbound\Internal\Challenge\OtpRepositoryPort;
use Otp\Application\Port\Outbound\Internal\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Exception\{OtpExpiredException, OtpMaxAttemptsException};
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpPurpose};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use User\Application\Port\Inbound\SignInEligibilityPort;

/**
 * Class VerifyMfaChallengeHandler
 *
 * Locks the user enrollment before the owner-bound login challenge and consumes both in one auth transaction.
 *
 * @category Handler
 */
final readonly class VerifyMfaChallengeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param TotpEnrollmentRepositoryPort $enrollments enrollment lock and persistence
   * @param OtpRepositoryPort $challenges bound challenge persistence
   * @param TotpServicePort $totp active authenticator verifier
   * @param SignInEligibilityPort $accounts fresh policy-aware sign-in eligibility
   * @param EventDispatcherPort $events explicitly auth-bound durable outbox dispatcher
   * @param AuthenticationPolicyPort $policy current project email-verification requirement
   *
   * @return void
   */
  public function __construct(
    private TotpEnrollmentRepositoryPort $enrollments,
    private OtpRepositoryPort $challenges,
    private TotpServicePort $totp,
    private SignInEligibilityPort $accounts,
    private EventDispatcherPort $events,
    private AuthenticationPolicyPort $policy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param VerifyMfaChallengeCommand $command signed challenge binding and proof
   *
   * @return VerifyMfaChallengeResult committed success or denial
   */
  public function __invoke(VerifyMfaChallengeCommand $command): VerifyMfaChallengeResult
  {
    if (ExpectedPurpose::LOGIN !== $command->purpose) {
      return $this->denied('invalid_token');
    }

    try {
      $token = ChallengeToken::fromString($command->challengeToken);
    } catch (InvalidArgumentException) {
      return $this->denied('invalid_token');
    }

    return $this->enrollments->withUserLock($command->userId, function () use ($command, $token): VerifyMfaChallengeResult {
      $enrollment = $this->enrollments->findByUserId($command->userId);
      if (!$this->accounts->isEligible($command->userId, $this->policy->emailVerificationRequired())) {
        return $this->denied('invalid_token');
      }
      $challenge = $this->challenges->findForVerification($token, $command->userId, OtpPurpose::LOGIN);
      if (null === $challenge) {
        return $this->denied('invalid_token');
      }
      if (!$challenge->canVerify()) {
        return $this->denied(match (true) {
          $challenge->isExpired() => 'expired',
          $challenge->isVerified() => 'invalid_token',
          default => 'max_attempts_exceeded',
        });
      }

      $valid = false;
      $recoveryConsumed = false;
      if ('recovery_code' === $command->method && OtpChannel::TOTP === $challenge->channel()) {
        $recoveryConsumed = $enrollment?->consumeRecoveryCode($command->proof) ?? false;
        $valid = $recoveryConsumed;
      } elseif ('otp' === $command->method && OtpChannel::TOTP === $challenge->channel()) {
        $secret = $enrollment?->activeSecret();
        $valid = null !== $secret && $this->totp->verify($command->proof, $secret);
      }

      try {
        $verified = 'otp' === $command->method && OtpChannel::TOTP !== $challenge->channel()
          ? $challenge->verify($command->proof)
          : $challenge->verifyExternal($valid);
      } catch (OtpExpiredException) {
        return $this->denied('expired');
      } catch (OtpMaxAttemptsException) {
        return $this->denied('max_attempts_exceeded');
      }
      if ($recoveryConsumed && null !== $enrollment && $verified) {
        $this->enrollments->save($enrollment);
      }
      $this->challenges->save($challenge);
      if ($recoveryConsumed && $verified) {
        $this->events->dispatch(new RecoveryCodeConsumedEvent($command->userId, 'login'));
      }

      return new VerifyMfaChallengeResult(new VerificationInfo(
        success: $verified,
        attemptsRemaining: $challenge->attemptsRemaining(),
        error: $verified ? null : 'Invalid verification code.',
        errorCode: $verified ? null : 'invalid_code',
      ));
    });
  }

  /**
   * Method denied
   *
   * @access private
   *
   * @param string $code stable denial code
   *
   * @return VerifyMfaChallengeResult generic denial without proof or owner details
   */
  private function denied(string $code): VerifyMfaChallengeResult
  {
    return new VerifyMfaChallengeResult(new VerificationInfo(false, 0, 'Invalid or expired MFA challenge.', $code));
  }
  // #endregion
}
