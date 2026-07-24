<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Registration\ResendRegistration;

use Auth\Application\UseCase\Command\Registration\ResendRegistration\{ResendRegistrationCommand, ResendRegistrationHandler, ResendRegistrationResult};
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\ChallengeInfo;
use Otp\Application\Contract\Challenge\{OtpChannel as ContractOtpChannel, OtpPurpose as ContractOtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ResendRegistrationHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendRegistrationHandler::class)]
final class ResendRegistrationHandlerTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testInvokeFailsWhenOtpNotFound(): void
  {
    /** @var OtpRepositoryPort&MockObject $otpRepository */
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn(null);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendRegistrationHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendRegistrationCommand(token: 'unknown-token'));

    self::assertFalse($result->success);
    self::assertSame(ResendRegistrationResult::ERROR_INVALID_TOKEN, $result->errorCode);
    self::assertSame('Invalid or expired verification token.', $result->message);
  }

  #[Test]
  public function testInvokeFailsWhenPurposeIsNotEmailVerification(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174010'),
      userId: 'user-10',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'user@example.com',
    );

    /** @var OtpRepositoryPort&MockObject $otpRepository */
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendRegistrationHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendRegistrationCommand(token: $otp->challengeToken()->value));

    self::assertFalse($result->success);
    self::assertSame(ResendRegistrationResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testInvokeFailsWhenOtpIsNotPending(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174020'),
      challengeToken: ChallengeToken::fromString('expired-token'),
      userId: 'user-20',
      purpose: OtpPurpose::EMAIL_VERIFICATION,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('-1 minute'),
      maxAttempts: 10,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-1 hour'),
    );

    /** @var OtpRepositoryPort&MockObject $otpRepository */
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendRegistrationHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendRegistrationCommand(token: 'expired-token'));

    self::assertFalse($result->success);
    self::assertSame(ResendRegistrationResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testInvokeFailsWhenResendIsWithinCooldown(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174030'),
      userId: 'user-30',
      purpose: OtpPurpose::EMAIL_VERIFICATION,
      channel: OtpChannel::EMAIL,
      recipient: 'user@example.com',
    );

    /** @var OtpRepositoryPort&MockObject $otpRepository */
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new ResendRegistrationHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendRegistrationCommand(token: $otp->challengeToken()->value));

    self::assertFalse($result->success);
    self::assertSame(ResendRegistrationResult::ERROR_RESEND_NOT_ALLOWED, $result->errorCode);
    self::assertGreaterThan(0, $result->retryAfter);
    self::assertLessThanOrEqual(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->retryAfter);
  }

  #[Test]
  public function testInvokeResendsVerificationCode(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174040'),
      challengeToken: ChallengeToken::fromString('pending-token'),
      userId: 'user-40',
      purpose: OtpPurpose::EMAIL_VERIFICATION,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'user@example.com',
      expiresAt: new DateTimeImmutable('+1 hour'),
      maxAttempts: 10,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-2 minutes'),
    );

    $expiresAt = new DateTimeImmutable('+1 hour');
    $challenge = new ChallengeInfo(
      challengeToken: 'new-challenge-token',
      maskedRecipient: 'us***@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 10,
    );

    /** @var OtpRepositoryPort&MockObject $otpRepository */
    $otpRepository = $this->createMock(OtpRepositoryPort::class);
    $otpRepository->expects(self::once())
      ->method('findByChallengeToken')
      ->willReturn($otp);

    /** @var OtpChallengePort&MockObject $otpChallenge */
    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::once())
      ->method('generate')
      ->with(
        'user-40',
        ContractOtpPurpose::EMAIL_VERIFICATION,
        ContractOtpChannel::EMAIL,
        'user@example.com',
      )
      ->willReturn($challenge);

    $handler = new ResendRegistrationHandler(
      otpRepository: $otpRepository,
      otpChallenge: $otpChallenge,
    );

    $result = $handler->__invoke(new ResendRegistrationCommand(token: 'pending-token'));

    self::assertTrue($result->success);
    self::assertSame('new-challenge-token', $result->challengeToken);
    self::assertSame('us***@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(10, $result->maxAttempts);
    self::assertSame(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->canResendIn);
    self::assertSame('A new verification code has been sent.', $result->message);
  }
  // #endregion
}
