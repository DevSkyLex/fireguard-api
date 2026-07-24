<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\{
  ConfirmPasswordResetCommand,
  ConfirmPasswordResetHandler,
  ConfirmPasswordResetResult
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

/**
 * Test ConfirmPasswordResetHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmPasswordResetHandler::class)]
final class ConfirmPasswordResetHandlerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  private const string OTP_ID = '123e4567-e89b-12d3-a456-426614174000';

  private const string TOKEN = 'a-valid-challenge-token-string';

  private const string RECIPIENT = 'jdoe@example.com';

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
    self::assertSame(ConfirmPasswordResetResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testPersistsAttemptAndFailsWhenCodeIsInvalid(): void
  {
    $otp = $this->makeOtp(userId: self::USER_ID);

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    // The consumed attempt must be persisted even when the code is wrong.
    $otpRepo->expects(self::once())->method('save')->with($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: $this->wrongCodeFor($otp))
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordResetResult::ERROR_INVALID_CODE, $result->errorCode);
    self::assertSame(4, $result->attemptsRemaining);
  }

  #[Test]
  public function testFailsWhenCodeHasExpired(): void
  {
    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($this->makeExpiredOtp());

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '123456')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordResetResult::ERROR_EXPIRED, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenMaxAttemptsExceeded(): void
  {
    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($this->makeMaxedOutOtp());

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '123456')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordResetResult::ERROR_MAX_ATTEMPTS, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenUserBehindOtpIsMissing(): void
  {
    $otp = $this->makeOtp(userId: self::USER_ID);

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    $otpRepo->expects(self::once())->method('save')->with($otp);

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn(null);
    // No password may be written when the user cannot be resolved.
    $userRepo->expects(self::never())->method('save');

    $result = $this->makeHandler(
      otpRepository: $otpRepo,
      userRepository: $userRepo,
    )($this->makeCommand(code: $otp->code()->plain()));

    self::assertFalse($result->success);
    self::assertSame(ConfirmPasswordResetResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testResetsPasswordAndRevokesEverythingOnSuccess(): void
  {
    $otp = $this->makeOtp(userId: self::USER_ID);
    $code = $otp->code()->plain();
    $user = $this->makeActiveUser();

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
    self::assertNull($result->errorCode);
    self::assertTrue($user->authenticate(self::NEW_PASSWORD));
  }
  // #endregion

  // #region Helpers
  private function makeHandler(
    ?OtpRepositoryPort $otpRepository = null,
    ?UserRepositoryPort $userRepository = null,
    ?SessionRepositoryPort $sessionRepository = null,
    ?TokenRevocationPort $tokenRevocation = null,
  ): ConfirmPasswordResetHandler {
    return new ConfirmPasswordResetHandler(
      otpRepository: $otpRepository ?? $this->createStub(OtpRepositoryPort::class),
      userRepository: $userRepository ?? $this->createStub(UserRepositoryPort::class),
      sessionRepository: $sessionRepository ?? $this->createStub(SessionRepositoryPort::class),
      tokenRevocation: $tokenRevocation ?? $this->createStub(TokenRevocationPort::class),
    );
  }

  private function makeCommand(string $code): ConfirmPasswordResetCommand
  {
    return new ConfirmPasswordResetCommand(
      token: self::TOKEN,
      code: $code,
      newPassword: self::NEW_PASSWORD,
    );
  }

  private function makeOtp(string $userId): Otp
  {
    return Otp::generate(
      id: new OtpId(self::OTP_ID),
      userId: $userId,
      purpose: OtpPurpose::PASSWORD_RESET,
      channel: OtpChannel::EMAIL,
      recipient: self::RECIPIENT,
    );
  }

  private function makeExpiredOtp(): Otp
  {
    return Otp::reconstitute(
      id: new OtpId(self::OTP_ID),
      challengeToken: ChallengeToken::fromString(self::TOKEN),
      userId: self::USER_ID,
      purpose: OtpPurpose::PASSWORD_RESET,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: self::RECIPIENT,
      expiresAt: new DateTimeImmutable('-1 hour'),
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-2 hours'),
    );
  }

  private function makeMaxedOutOtp(): Otp
  {
    return Otp::reconstitute(
      id: new OtpId(self::OTP_ID),
      challengeToken: ChallengeToken::fromString(self::TOKEN),
      userId: self::USER_ID,
      purpose: OtpPurpose::PASSWORD_RESET,
      channel: OtpChannel::EMAIL,
      codeHash: OtpCode::generate()->hash(),
      recipient: self::RECIPIENT,
      expiresAt: new DateTimeImmutable('+15 minutes'),
      maxAttempts: 5,
      attempts: 5,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-1 minute'),
    );
  }

  private function makeActiveUser(): User
  {
    $user = User::register(
      id: new UserId(self::USER_ID),
      username: new Username('jdoe'),
      email: new Email(self::RECIPIENT),
      password: HashedPassword::fromPlain('OldP@ssw0rd!'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
    $user->verifyEmail(new TestEventIdProvider());
    $user->activate();

    return $user;
  }

  private function wrongCodeFor(Otp $otp): string
  {
    return '000000' === $otp->code()->plain() ? '999999' : '000000';
  }
  // #endregion
}
