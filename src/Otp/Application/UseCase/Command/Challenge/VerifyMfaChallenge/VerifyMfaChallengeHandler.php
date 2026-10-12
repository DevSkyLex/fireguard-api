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
use Otp\Domain\Model\Otp;
use Otp\Domain\Model\Totp\TotpEnrollment;
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

    return $this->enrollments->withUserLock($command->userId, fn (): VerifyMfaChallengeResult => $this->verifyLocked($command, $token));
  }

  /**
   * Method verifyLocked
   *
   * Resolves current eligibility and enrollment while holding the account lock.
   *
   * @access private
   *
   * @param VerifyMfaChallengeCommand $command signed challenge owner and proof
   * @param ChallengeToken $token validated native challenge identity
   *
   * @return VerifyMfaChallengeResult verified challenge result or generic denial
   */
  private function verifyLocked(VerifyMfaChallengeCommand $command, ChallengeToken $token): VerifyMfaChallengeResult
  {
    $enrollment = $this->enrollments->findByUserId($command->userId);
    if (!$this->accounts->isEligible($command->userId, $this->policy->emailVerificationRequired())) {
      return $this->denied('invalid_token');
    }

    return $this->verifyChallenge($command, $token, $enrollment);
  }

  /**
   * Method verifyChallenge
   *
   * Acquires the owner-bound challenge lock after the enrollment lock and checks its current state.
   *
   * @access private
   *
   * @param VerifyMfaChallengeCommand $command signed owner and proof method
   * @param ChallengeToken $token validated challenge identity
   * @param TotpEnrollment|null $enrollment freshly locked authenticator state
   *
   * @return VerifyMfaChallengeResult current attempt or non-mutating state denial
   */
  private function verifyChallenge(VerifyMfaChallengeCommand $command, ChallengeToken $token, ?TotpEnrollment $enrollment): VerifyMfaChallengeResult
  {
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

    return $this->verifyAttempt($command, $challenge, $enrollment);
  }

  /**
   * Method authenticatorProof
   *
   * Consumes a recovery proof only inside the same account lock as challenge verification.
   *
   * @access private
   *
   * @param VerifyMfaChallengeCommand $command requested proof method and material
   * @param Otp $challenge currently locked owner-bound challenge
   * @param TotpEnrollment|null $enrollment freshly locked authenticator state
   *
   * @return array{bool, bool} proof validity and whether a recovery code was consumed
   */
  private function authenticatorProof(VerifyMfaChallengeCommand $command, Otp $challenge, ?TotpEnrollment $enrollment): array
  {
    $valid = false;
    $recoveryConsumed = false;
    if ('recovery_code' === $command->method && OtpChannel::TOTP === $challenge->channel()) {
      $recoveryConsumed = $enrollment?->consumeRecoveryCode($command->proof) ?? false;
      $valid = $recoveryConsumed;
    } elseif ('otp' === $command->method && OtpChannel::TOTP === $challenge->channel()) {
      $secret = $enrollment?->activeSecret();
      $valid = null !== $secret && $this->totp->verify($command->proof, $secret);
    }

    return [$valid, $recoveryConsumed];
  }

  /**
   * Method verifyAttempt
   *
   * Saves the challenge attempt and any consumed recovery code together before its durable event.
   *
   * @access private
   *
   * @param VerifyMfaChallengeCommand $command current signed challenge and proof
   * @param Otp $challenge currently locked owner-bound challenge
   * @param TotpEnrollment|null $enrollment freshly locked authenticator state
   *
   * @return VerifyMfaChallengeResult original bounded verification outcome
   */
  private function verifyAttempt(VerifyMfaChallengeCommand $command, Otp $challenge, ?TotpEnrollment $enrollment): VerifyMfaChallengeResult
  {
    [$valid, $recoveryConsumed] = $this->authenticatorProof($command, $challenge, $enrollment);

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
