<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordChange;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange\{
  ConfirmPasswordChangeCommand,
  ConfirmPasswordChangeHandler,
  ConfirmPasswordChangeResult
};
use DateTimeImmutable;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

#[CoversClass(ConfirmPasswordChangeHandler::class)]
final class ConfirmPasswordChangeHandlerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  private const string OTP_ID = '123e4567-e89b-12d3-a456-426614174000';

  private const string NEW_PASSWORD = 'NewSecureP@ss1!';

  // #region Methods
  #[Test]
  public function testFailsWhenTokenIsUnknown(): void
  {
    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn(null);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '123456')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenOtpBelongsToAnotherUser(): void
  {
    $otp = $this->makeOtp(userId: 'another-user', purpose: OtpPurpose::SENSITIVE_OPERATION);

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: $otp->code()->plain())
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenOtpHasWrongPurpose(): void
  {
    $otp = $this->makeOtp(userId: self::USER_ID, purpose: OtpPurpose::PASSWORD_RESET);

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: $otp->code()->plain())
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenCodeIsInvalid(): void
  {
    $otp = $this->makeOtp(userId: self::USER_ID, purpose: OtpPurpose::SENSITIVE_OPERATION);

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    // Failed attempt must be persisted
    $otpRepo->expects(self::once())->method('save')->with($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '000000')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordChangeResult::ERROR_INVALID_CODE, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenOtpHasExpired(): void
  {
    $otp = $this->makeExpiredOtp();

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    // The aggregate throws before mutating, so nothing is persisted.
    $otpRepo->expects(self::never())->method('save');

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '123456')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordChangeResult::ERROR_EXPIRED, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenOtpMaxAttemptsAreExhausted(): void
  {
    $otp = $this->makeExhaustedOtp();

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    $otpRepo->expects(self::never())->method('save');

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '123456')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordChangeResult::ERROR_MAX_ATTEMPTS, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenTheUserBehindAValidOtpNoLongerExists(): void
  {
    $otp = $this->makeOtp(userId: self::USER_ID, purpose: OtpPurpose::SENSITIVE_OPERATION);

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn(null);
    $userRepo->expects(self::never())->method('save');

    /** @var SessionRepositoryPort&MockObject $sessionRepo */
    $sessionRepo = $this->createMock(SessionRepositoryPort::class);
    $sessionRepo->expects(self::never())->method('revokeAllForUser');

    /** @var TokenRevocationPort&MockObject $tokenRevocation */
    $tokenRevocation = $this->createMock(TokenRevocationPort::class);
    $tokenRevocation->expects(self::never())->method('revokeAllUserTokens');

    $handler = $this->makeHandler(
      otpRepository: $otpRepo,
      userRepository: $userRepo,
      sessionRepository: $sessionRepo,
      tokenRevocation: $tokenRevocation,
    );

    $result = $handler($this->makeCommand(code: $otp->code()->plain()));

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testChangesPasswordAndRevokesSessionsOnSuccess(): void
  {
    $otp = $this->makeOtp(userId: self::USER_ID, purpose: OtpPurpose::SENSITIVE_OPERATION);
    $code = $otp->code()->plain();
    $user = $this->makeUser();

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    $otpRepo->expects(self::once())->method('save')->with($otp);

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->expects(self::once())->method('save')->with($user);

    /** @var SessionRepositoryPort&MockObject $sessionRepo */
    $sessionRepo = $this->createMock(SessionRepositoryPort::class);
    $sessionRepo->expects(self::once())->method('revokeAllForUser')->with(self::USER_ID);

    /** @var TokenRevocationPort&MockObject $tokenRevocation */
    $tokenRevocation = $this->createMock(TokenRevocationPort::class);
    $tokenRevocation->expects(self::once())->method('revokeAllUserTokens')->with(self::USER_ID);

    $handler = $this->makeHandler(
      otpRepository: $otpRepo,
      userRepository: $userRepo,
      sessionRepository: $sessionRepo,
      tokenRevocation: $tokenRevocation,
    );

    $result = $handler($this->makeCommand(code: $code));

    self::assertTrue($result->success);
    self::assertTrue($user->authenticate(self::NEW_PASSWORD));
  }
  // #endregion

  // #region Helpers
  private function makeHandler(
    ?OtpRepositoryPort $otpRepository = null,
    ?UserRepositoryPort $userRepository = null,
    ?SessionRepositoryPort $sessionRepository = null,
    ?TokenRevocationPort $tokenRevocation = null,
  ): ConfirmPasswordChangeHandler {
    return new ConfirmPasswordChangeHandler(
      otpRepository: $otpRepository ?? $this->createStub(OtpRepositoryPort::class),
      userRepository: $userRepository ?? $this->createStub(UserRepositoryPort::class),
      sessionRepository: $sessionRepository ?? $this->createStub(SessionRepositoryPort::class),
      tokenRevocation: $tokenRevocation ?? $this->createStub(TokenRevocationPort::class),
    );
  }

  private function makeCommand(string $code): ConfirmPasswordChangeCommand
  {
    return new ConfirmPasswordChangeCommand(
      userId: self::USER_ID,
      token: 'a-valid-challenge-token-string',
      code: $code,
      newPassword: self::NEW_PASSWORD,
    );
  }

  private function makeOtp(string $userId, OtpPurpose $purpose): Otp
  {
    return Otp::generate(
      id: new OtpId(self::OTP_ID),
      userId: $userId,
      purpose: $purpose,
      channel: OtpChannel::EMAIL,
      recipient: 'jdoe@example.com',
    );
  }

  private function makeExpiredOtp(): Otp
  {
    return Otp::reconstitute(
      id: new OtpId(self::OTP_ID),
      challengeToken: ChallengeToken::fromString('a-valid-challenge-token-string'),
      userId: self::USER_ID,
      purpose: OtpPurpose::SENSITIVE_OPERATION,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'jdoe@example.com',
      expiresAt: new DateTimeImmutable('-1 minute'),
      maxAttempts: 3,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-10 minutes'),
    );
  }

  private function makeExhaustedOtp(): Otp
  {
    return Otp::reconstitute(
      id: new OtpId(self::OTP_ID),
      challengeToken: ChallengeToken::fromString('a-valid-challenge-token-string'),
      userId: self::USER_ID,
      purpose: OtpPurpose::SENSITIVE_OPERATION,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: 'jdoe@example.com',
      expiresAt: new DateTimeImmutable('+5 minutes'),
      maxAttempts: 3,
      attempts: 3,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-1 minute'),
    );
  }

  private function makeUser(): User
  {
    $user = User::register(
      id: new UserId(self::USER_ID),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('OldP@ssw0rd!'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
    $user->verifyEmail(new TestEventIdProvider());
    $user->activate();

    return $user;
  }
  // #endregion
}
