<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset;

use Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset\{
  ResendPasswordResetCommand,
  ResendPasswordResetHandler,
  ResendPasswordResetResult
};
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpPurpose as ContractOtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ResendPasswordResetHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendPasswordResetHandler::class)]
final class ResendPasswordResetHandlerTest extends TestCase
{
  private const string OTP_ID = '123e4567-e89b-12d3-a456-426614174040';

  private const string CHALLENGE_TOKEN = 'reset-token-abc';

  // #region Methods
  #[Test]
  public function testResendsPasswordResetCodeWhenChallengeIsPending(): void
  {
    $expiresAt = new DateTimeImmutable('2026-07-24 12:00:00');
    $otp = $this->pendingResetOtp();

    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::once())
      ->method('generate')
      ->with(
        'user-1',
        ContractOtpPurpose::PASSWORD_RESET,
        self::anything(),
        'user@example.com',
      )
      ->willReturn(new ChallengeInfo(
        challengeToken: 'new-challenge-token',
        maskedRecipient: 'us***@example.com',
        expiresAt: $expiresAt,
        maxAttempts: 5,
      ));

    $handler = new ResendPasswordResetHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendPasswordResetCommand(token: self::CHALLENGE_TOKEN));

    self::assertTrue($result->success);
    self::assertNull($result->errorCode);
    self::assertSame('new-challenge-token', $result->challengeToken);
    self::assertSame('us***@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
    self::assertSame(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->canResendIn);
    self::assertSame('A new password reset code has been sent.', $result->message);
  }

  #[Test]
  public function testFailsWhenChallengeTokenIsUnknown(): void
  {
    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn(null);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendPasswordResetHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendPasswordResetCommand(token: 'does-not-exist'));

    self::assertFalse($result->success);
    self::assertSame(ResendPasswordResetResult::ERROR_INVALID_TOKEN, $result->errorCode);
    self::assertSame('Invalid or expired reset code. Please check the code, or request a new reset.', $result->message);
  }

  #[Test]
  public function testFailsWhenPurposeIsNotPasswordReset(): void
  {
    $otp = $this->pendingResetOtp(purpose: OtpPurpose::LOGIN);

    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendPasswordResetHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendPasswordResetCommand(token: self::CHALLENGE_TOKEN));

    self::assertFalse($result->success);
    self::assertSame(ResendPasswordResetResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenChallengeIsNotPending(): void
  {
    // An expired challenge reports a non-pending status.
    $otp = $this->pendingResetOtp(expiresAt: new DateTimeImmutable('-1 minute'));

    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendPasswordResetHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendPasswordResetCommand(token: self::CHALLENGE_TOKEN));

    self::assertFalse($result->success);
    self::assertSame(ResendPasswordResetResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenResendIsRequestedWithinCooldown(): void
  {
    // Freshly created challenge is still inside the resend cooldown window.
    $otp = $this->pendingResetOtp(createdAt: new DateTimeImmutable());

    $otpRepository = $this->createStub(OtpRepositoryPort::class);
    $otpRepository->method('findByChallengeToken')->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendPasswordResetHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendPasswordResetCommand(token: self::CHALLENGE_TOKEN));

    self::assertFalse($result->success);
    self::assertSame(ResendPasswordResetResult::ERROR_RESEND_NOT_ALLOWED, $result->errorCode);
    self::assertGreaterThan(0, $result->retryAfter);
    self::assertLessThanOrEqual(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->retryAfter);
  }
  // #endregion

  // #region Helpers
  private function pendingResetOtp(
    OtpPurpose $purpose = OtpPurpose::PASSWORD_RESET,
    ?DateTimeImmutable $createdAt = null,
    ?DateTimeImmutable $expiresAt = null,
  ): Otp {
    return Otp::reconstitute(
      id: new OtpId(self::OTP_ID),
      challengeToken: ChallengeToken::fromString(self::CHALLENGE_TOKEN),
      userId: 'user-1',
      purpose: $purpose,
      channel: OtpChannel::EMAIL,
      codeHash: 'hashed-code',
      recipient: 'user@example.com',
      expiresAt: $expiresAt ?? new DateTimeImmutable('+15 minutes'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: $createdAt ?? new DateTimeImmutable('-1 hour'),
    );
  }
  // #endregion
}
