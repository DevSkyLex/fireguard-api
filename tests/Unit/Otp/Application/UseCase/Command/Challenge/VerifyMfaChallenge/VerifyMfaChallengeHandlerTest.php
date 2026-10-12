<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Challenge\VerifyMfaChallenge;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\OtpPurpose as Purpose;
use Otp\Application\Port\Outbound\Internal\Challenge\OtpRepositoryPort;
use Otp\Application\Port\Outbound\Internal\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\UseCase\Command\Challenge\VerifyMfaChallenge\{VerifyMfaChallengeCommand, VerifyMfaChallengeHandler};
use Otp\Domain\Model\Otp;
use Otp\Domain\Model\Totp\TotpEnrollment;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpGenerationOptions, OtpId, OtpPurpose, RecoveryCode, TotpSecret};
use PHPUnit\Framework\TestCase;
use User\Application\Port\Inbound\SignInEligibilityPort;

/**
 * Class VerifyMfaChallengeHandlerTest
 *
 * Proves durable attempts, signed owner binding and one-time recovery/challenge consumption.
 *
 * @category Test
 */
final class VerifyMfaChallengeHandlerTest extends TestCase
{
  private const string CODE = 'abcd-abcd-abcd-abcd-abcd-abcd-abcd-abcd';

  public function testRecoveryProofAndChallengeConsumeOnlyOnceAndUsedProofDeniesAnotherChallenge(): void
  {
    $enrollment = $this->enrollment();
    $first = $this->challenge();
    $second = $this->challenge();
    $repo = $this->createMock(OtpRepositoryPort::class);
    $repo->method('findForVerification')->willReturnOnConsecutiveCalls($first, $first, $second);
    $repo->expects(self::exactly(2))->method('save');
    $enrollments = $this->repositories($enrollment);
    $enrollments->expects(self::once())->method('save')->with($enrollment);
    $handler = $this->handler($enrollments, $repo);
    $command = new VerifyMfaChallengeCommand($first->challengeToken()->value, 'user', Purpose::LOGIN, 'recovery_code', self::CODE);
    self::assertTrue($handler($command)->verification->success);
    self::assertFalse($handler($command)->verification->success);
    self::assertFalse($handler(new VerifyMfaChallengeCommand($second->challengeToken()->value, 'user', Purpose::LOGIN, 'recovery_code', self::CODE))->verification->success);
    self::assertTrue($first->isVerified());
    self::assertFalse($second->isVerified());
    self::assertSame(1, $second->attempts());
    self::assertSame(0, $enrollment->recoveryCodesRemaining());
  }

  public function testWrongRecoveryProofPersistsAttemptWithoutConsumingLot(): void
  {
    $enrollment = $this->enrollment();
    $challenge = $this->challenge();
    $repo = $this->createMock(OtpRepositoryPort::class);
    $repo->method('findForVerification')->willReturn($challenge);
    $repo->expects(self::once())->method('save')->with($challenge);
    $enrollments = $this->repositories($enrollment);
    $enrollments->expects(self::never())->method('save');
    $result = $this->handler($enrollments, $repo)(new VerifyMfaChallengeCommand($challenge->challengeToken()->value, 'user', Purpose::LOGIN, 'recovery_code', '11111111111111111111111111111111'));
    self::assertFalse($result->verification->success);
    self::assertSame('invalid_code', $result->verification->errorCode);
    self::assertSame(4, $result->verification->attemptsRemaining);
    self::assertSame(1, $enrollment->recoveryCodesRemaining());
  }

  public function testExpiredChallengeDoesNotConsumeRecoveryProof(): void
  {
    $enrollment = $this->enrollment();
    $challenge = $this->challenge(ttl: -10);
    $repo = $this->createMock(OtpRepositoryPort::class);
    $repo->method('findForVerification')->willReturn($challenge);
    $repo->expects(self::never())->method('save');
    $enrollments = $this->repositories($enrollment);
    $enrollments->expects(self::never())->method('save');
    $result = $this->handler($enrollments, $repo)(new VerifyMfaChallengeCommand($challenge->challengeToken()->value, 'user', Purpose::LOGIN, 'recovery_code', self::CODE));
    self::assertSame('expired', $result->verification->errorCode);
    self::assertSame(1, $enrollment->recoveryCodesRemaining());
  }

  public function testMissingBoundChallengeDeniesWithoutAnyProofConsumption(): void
  {
    $enrollment = $this->enrollment();
    $repo = $this->createMock(OtpRepositoryPort::class);
    $repo->expects(self::once())->method('findForVerification')->with(self::isInstanceOf(ChallengeToken::class), 'user', OtpPurpose::LOGIN)->willReturn(null);
    $repo->expects(self::never())->method('save');
    $enrollments = $this->repositories($enrollment);
    $enrollments->expects(self::never())->method('save');
    self::assertFalse($this->handler($enrollments, $repo)(new VerifyMfaChallengeCommand('foreign-owner-token', 'user', Purpose::LOGIN, 'recovery_code', self::CODE))->verification->success);
    self::assertSame(1, $enrollment->recoveryCodesRemaining());
  }

  public function testRecoveryCannotVerifyEmailChallengeAndStillPersistsAttempt(): void
  {
    $challenge = $this->challenge(OtpChannel::EMAIL);
    $repo = $this->createMock(OtpRepositoryPort::class);
    $repo->method('findForVerification')->willReturn($challenge);
    $repo->expects(self::once())->method('save');
    $enrollment = $this->enrollment();
    $enrollments = $this->repositories($enrollment);
    $enrollments->expects(self::never())->method('save');
    self::assertFalse($this->handler($enrollments, $repo)(new VerifyMfaChallengeCommand('token', 'user', Purpose::LOGIN, 'recovery_code', self::CODE))->verification->success);
    self::assertSame(1, $enrollment->recoveryCodesRemaining());
    self::assertSame(1, $challenge->attempts());
  }

  public function testInactiveAccountDoesNotReadOrConsumeChallenge(): void
  {
    $repo = $this->createMock(OtpRepositoryPort::class);
    $repo->expects(self::never())->method('findForVerification');
    $accounts = $this->createStub(SignInEligibilityPort::class);
    $accounts->method('isEligible')->willReturn(false);
    $enrollment = $this->enrollment();
    $enrollments = $this->repositories($enrollment);
    $enrollments->expects(self::never())->method('save');
    $handler = new VerifyMfaChallengeHandler($enrollments, $repo, $this->createStub(TotpServicePort::class), $accounts, events: $this->createStub(\Shared\Application\Port\Outbound\EventDispatcherPort::class), policy: $this->createStub(\Auth\Application\Port\Outbound\AuthenticationPolicyPort::class));
    self::assertFalse($handler(new VerifyMfaChallengeCommand('token', 'user', Purpose::LOGIN, 'recovery_code', self::CODE))->verification->success);
    self::assertSame(1, $enrollment->recoveryCodesRemaining());
  }

  public function testMissingOrDisabledEnrollmentCannotRecoverAndPersistsDenialAttempt(): void
  {
    $disabled = $this->enrollment();
    $disabled->disable(true);
    foreach ([null, $disabled] as $enrollment) {
      $challenge = $this->challenge();
      $repo = $this->createMock(OtpRepositoryPort::class);
      $repo->method('findForVerification')->willReturn($challenge);
      $repo->expects(self::once())->method('save')->with($challenge);
      $enrollments = $this->repositories($enrollment);
      $enrollments->expects(self::never())->method('save');
      $result = $this->handler($enrollments, $repo)(new VerifyMfaChallengeCommand('token', 'user', Purpose::LOGIN, 'recovery_code', self::CODE));
      self::assertFalse($result->verification->success);
      self::assertSame(1, $challenge->attempts());
    }
  }

  public function testWrongPurposeDoesNotLockOrReadChallenges(): void
  {
    $repo = $this->createMock(OtpRepositoryPort::class);
    $repo->expects(self::never())->method('findForVerification');
    $enrollments = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $enrollments->expects(self::never())->method('withUserLock');
    $result = $this->handler($enrollments, $repo)(new VerifyMfaChallengeCommand('token', 'user', Purpose::SENSITIVE_OPERATION, 'recovery_code', self::CODE));
    self::assertSame('invalid_token', $result->verification->errorCode);
  }

  public function testOrdinaryTotpAndEmailProofsKeepTheirExistingMethods(): void
  {
    foreach ([OtpChannel::TOTP, OtpChannel::EMAIL] as $channel) {
      $enrollment = $this->enrollment();
      $challenge = $this->challenge($channel);
      $repo = $this->createMock(OtpRepositoryPort::class);
      $repo->method('findForVerification')->willReturn($challenge);
      $repo->expects(self::once())->method('save')->with($challenge);
      $enrollments = $this->repositories($enrollment);
      $enrollments->expects(self::never())->method('save');
      $totp = $this->createStub(TotpServicePort::class);
      $totp->method('verify')->willReturn(true);
      $accounts = $this->createStub(SignInEligibilityPort::class);
      $accounts->method('isEligible')->willReturn(true);
      $handler = new VerifyMfaChallengeHandler($enrollments, $repo, $totp, $accounts, events: $this->createStub(\Shared\Application\Port\Outbound\EventDispatcherPort::class), policy: $this->createStub(\Auth\Application\Port\Outbound\AuthenticationPolicyPort::class));
      $proof = OtpChannel::TOTP === $channel ? '123456' : $challenge->code()->plain();
      self::assertTrue($handler(new VerifyMfaChallengeCommand('token', 'user', Purpose::LOGIN, 'otp', $proof))->verification->success);
      self::assertSame(1, $enrollment->recoveryCodesRemaining());
    }
  }

  public function testPendingVerificationEmailMfaFollowsCurrentProjectRequirement(): void
  {
    foreach ([true, false] as $requireVerification) {
      $challenge = $this->challenge(OtpChannel::EMAIL);
      $repo = $this->createMock(OtpRepositoryPort::class);
      $repo->expects($requireVerification ? self::never() : self::once())->method('findForVerification')->willReturn($challenge);
      $repo->expects($requireVerification ? self::never() : self::once())->method('save')->with($challenge);
      $enrollments = $this->repositories(null);
      $enrollments->expects(self::never())->method('save');
      $accounts = $this->createMock(SignInEligibilityPort::class);
      $accounts->expects(self::once())->method('isEligible')->with('user', $requireVerification)->willReturn(!$requireVerification);
      $policy = $this->createMock(\Auth\Application\Port\Outbound\AuthenticationPolicyPort::class);
      $policy->expects(self::once())->method('emailVerificationRequired')->willReturn($requireVerification);
      $handler = new VerifyMfaChallengeHandler($enrollments, $repo, $this->createStub(TotpServicePort::class), $accounts, $this->createStub(\Shared\Application\Port\Outbound\EventDispatcherPort::class), $policy);
      $result = $handler(new VerifyMfaChallengeCommand('token', 'user', Purpose::LOGIN, 'otp', $challenge->code()->plain()));
      self::assertSame(!$requireVerification, $result->verification->success);
      self::assertSame(!$requireVerification, $challenge->isVerified());
      self::assertSame($requireVerification ? 0 : 1, $challenge->attempts());
    }
  }

  private function enrollment(): TotpEnrollment
  {
    $enrollment = TotpEnrollment::startEnrollment('user', new TotpSecret('JBSWY3DPEHPK3PXP'), 5);
    $enrollment->confirmPending(true);
    $hash = RecoveryCode::hash(self::CODE);
    self::assertIsString($hash);
    $enrollment->replaceRecoveryCodes([$hash], new DateTimeImmutable());

    return $enrollment;
  }

  private function challenge(OtpChannel $channel = OtpChannel::TOTP, int $ttl = 300): Otp
  {
    return Otp::generate(new OtpId('11111111-1111-4111-8111-111111111111'), 'user', OtpPurpose::LOGIN, $channel, 'user@example.test', new OtpGenerationOptions(ttlSeconds: $ttl, maxAttempts: 5));
  }

  /**
   * @return TotpEnrollmentRepositoryPort&\PHPUnit\Framework\MockObject\MockObject enrollment mock
   */
  private function repositories(?TotpEnrollment $enrollment): TotpEnrollmentRepositoryPort
  {
    $repo = $this->createMock(TotpEnrollmentRepositoryPort::class);
    $repo->method('withUserLock')->willReturnCallback(static fn (string $user, callable $operation): mixed => $operation());
    $repo->method('findByUserId')->willReturn($enrollment);

    return $repo;
  }

  private function handler(TotpEnrollmentRepositoryPort $enrollments, OtpRepositoryPort $repo): VerifyMfaChallengeHandler
  {
    $accounts = $this->createStub(SignInEligibilityPort::class);
    $accounts->method('isEligible')->willReturn(true);

    return new VerifyMfaChallengeHandler($enrollments, $repo, $this->createStub(TotpServicePort::class), $accounts, events: $this->createStub(\Shared\Application\Port\Outbound\EventDispatcherPort::class), policy: $this->createStub(\Auth\Application\Port\Outbound\AuthenticationPolicyPort::class));
  }
}
