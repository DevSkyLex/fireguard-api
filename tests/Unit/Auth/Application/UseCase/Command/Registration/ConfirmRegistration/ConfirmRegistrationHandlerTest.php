<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Registration\ConfirmRegistration;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort};
use Auth\Application\UseCase\Command\Registration\ConfirmRegistration\{
  ConfirmRegistrationCommand,
  ConfirmRegistrationHandler,
  ConfirmRegistrationResult
};
use Auth\Domain\ValueObject\Scope\DefaultScopes;
use DateTimeImmutable;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpId, OtpPurpose};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventBusPort, EventDispatcherPort};
use Tests\Helper\TestEventIdProvider;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;

/**
 * Test ConfirmRegistrationHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmRegistrationHandler::class)]
final class ConfirmRegistrationHandlerTest extends TestCase
{
  // #region Constants
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  private const string OTP_ID = '123e4567-e89b-12d3-a456-426614174000';

  private const string TOKEN = 'a-valid-challenge-token-string';

  private const string EMAIL = 'newuser@example.com';

  private const string IP_ADDRESS = '203.0.113.10';

  private const string USER_AGENT = 'PHPUnit';

  private const string ACCESS_TOKEN = 'access-token-value';

  private const string REFRESH_TOKEN = 'refresh-token-value';

  private const string ACCESS_TOKEN_ID = 'access-token-id';

  private const string REFRESH_TOKEN_ID = 'refresh-token-id';

  private const string DECODED_ACCESS_TOKEN_ID = 'decoded-access-token-id';

  private const string DECODED_REFRESH_TOKEN_ID = 'decoded-refresh-token-id';

  private const string UNUSED_CODE_HASH = 'unused-code-hash';
  // #endregion

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
    self::assertSame(ConfirmRegistrationResult::ERROR_INVALID_TOKEN, $result->errorCode);
    self::assertNull($result->accessToken);
  }

  #[Test]
  public function testFailsWhenOtpHasWrongPurpose(): void
  {
    $otp = $this->makeOtp(purpose: OtpPurpose::PASSWORD_RESET);

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: $otp->code()->plain())
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmRegistrationResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testFailsAndPersistsAttemptWhenCodeIsInvalid(): void
  {
    $otp = $this->makeOtp();

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    // The consumed (failed) attempt must be persisted.
    $otpRepo->expects(self::once())->method('save')->with($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: 'definitely-wrong-code')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmRegistrationResult::ERROR_INVALID_CODE, $result->errorCode);
    self::assertSame(9, $result->attemptsRemaining);
  }

  #[Test]
  public function testFailsWhenCodeHasExpired(): void
  {
    $otp = $this->reconstitutedOtp(
      expiresAt: new DateTimeImmutable('-1 hour'),
      maxAttempts: 10,
      attempts: 0,
    );

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '123456')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmRegistrationResult::ERROR_EXPIRED, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenMaxAttemptsExceeded(): void
  {
    $otp = $this->reconstitutedOtp(
      expiresAt: new DateTimeImmutable('+1 hour'),
      maxAttempts: 10,
      attempts: 10,
    );

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    $result = $this->makeHandler(otpRepository: $otpRepo)(
      $this->makeCommand(code: '123456')
    );

    self::assertFalse($result->success);
    self::assertSame(ConfirmRegistrationResult::ERROR_MAX_ATTEMPTS, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenUserNotFound(): void
  {
    $otp = $this->makeOtp();

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    $otpRepo->expects(self::once())->method('save')->with($otp);

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn(null);

    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::never())->method('generateTokens');

    $result = $this->makeHandler(
      otpRepository: $otpRepo,
      userRepository: $userRepo,
      tokenService: $tokenService,
    )($this->makeCommand(code: $otp->code()->plain()));

    self::assertFalse($result->success);
    self::assertSame(ConfirmRegistrationResult::ERROR_INVALID_TOKEN, $result->errorCode);
  }

  #[Test]
  public function testVerifiesEmailIssuesTokensAndReturnsSuccess(): void
  {
    $otp = $this->makeOtp();
    $user = $this->makeUser();

    /** @var OtpRepositoryPort&MockObject $otpRepo */
    $otpRepo = $this->createMock(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);
    $otpRepo->expects(self::once())->method('save')->with($otp);

    /** @var UserRepositoryPort&MockObject $userRepo */
    $userRepo = $this->createMock(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);
    $userRepo->expects(self::once())->method('save')->with($user);

    /** @var EventBusPort&MockObject $eventBus */
    $eventBus = $this->createMock(EventBusPort::class);
    $eventBus->expects(self::atLeastOnce())->method('publish');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::exactly(2))->method('dispatch');

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('recordSession')
      ->with(
        self::USER_ID,
        self::IP_ADDRESS,
        self::USER_AGENT,
        self::ACCESS_TOKEN_ID,
        self::REFRESH_TOKEN_ID,
        false,
      );

    $tokenService = $this->createStub(JwtTokenServicePort::class);
    $tokenService->method('generateTokens')->willReturn($this->defaultTokens());

    $handler = $this->makeHandler(
      otpRepository: $otpRepo,
      userRepository: $userRepo,
      eventBus: $eventBus,
      tokenService: $tokenService,
      sessionTracking: $sessionTracking,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler($this->makeCommand(code: $otp->code()->plain()));

    self::assertTrue($result->success);
    self::assertSame(self::ACCESS_TOKEN, $result->accessToken);
    self::assertSame(self::REFRESH_TOKEN, $result->refreshToken);
    self::assertSame('Bearer', $result->tokenType);
    self::assertSame(3600, $result->expiresIn);
    self::assertSame(DefaultScopes::USER_SCOPES, $result->scopes);
    self::assertTrue($user->isEmailVerified());
  }

  #[Test]
  public function testRecordsSessionUsingDecodedRefreshTokenWhenIdsAbsent(): void
  {
    $otp = $this->makeOtp();
    $user = $this->makeUser();

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);

    $tokenService = $this->createStub(JwtTokenServicePort::class);
    $tokenService->method('generateTokens')->willReturn($this->tokensWithoutIds());
    $tokenService->method('decodeRefreshToken')->willReturn([
      'refresh_token_id' => self::DECODED_REFRESH_TOKEN_ID,
      'access_token_id' => self::DECODED_ACCESS_TOKEN_ID,
      'user_id' => self::USER_ID,
      'scopes' => DefaultScopes::USER_SCOPES,
      'expires_at' => 1_800_000_000,
    ]);

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('recordSession')
      ->with(
        self::USER_ID,
        self::IP_ADDRESS,
        self::USER_AGENT,
        self::DECODED_ACCESS_TOKEN_ID,
        self::DECODED_REFRESH_TOKEN_ID,
        false,
      );

    $handler = $this->makeHandler(
      otpRepository: $otpRepo,
      userRepository: $userRepo,
      tokenService: $tokenService,
      sessionTracking: $sessionTracking,
    );

    $result = $handler($this->makeCommand(code: $otp->code()->plain()));

    self::assertTrue($result->success);
  }

  #[Test]
  public function testSucceedsEvenWhenSessionTrackingFails(): void
  {
    $otp = $this->makeOtp();
    $user = $this->makeUser();

    $otpRepo = $this->createStub(OtpRepositoryPort::class);
    $otpRepo->method('findByChallengeToken')->willReturn($otp);

    $userRepo = $this->createStub(UserRepositoryPort::class);
    $userRepo->method('findById')->willReturn($user);

    $tokenService = $this->createStub(JwtTokenServicePort::class);
    $tokenService->method('generateTokens')->willReturn($this->defaultTokens());

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('recordSession')
      ->willThrowException(new RuntimeException('tracking unavailable'));

    $handler = $this->makeHandler(
      otpRepository: $otpRepo,
      userRepository: $userRepo,
      tokenService: $tokenService,
      sessionTracking: $sessionTracking,
    );

    $result = $handler($this->makeCommand(code: $otp->code()->plain()));

    self::assertTrue($result->success);
    self::assertSame(self::ACCESS_TOKEN, $result->accessToken);
  }
  // #endregion

  // #region Helpers
  private function makeHandler(
    ?OtpRepositoryPort $otpRepository = null,
    ?UserRepositoryPort $userRepository = null,
    ?EventBusPort $eventBus = null,
    ?JwtTokenServicePort $tokenService = null,
    ?SessionTrackingPort $sessionTracking = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): ConfirmRegistrationHandler {
    return new ConfirmRegistrationHandler(
      otpRepository: $otpRepository ?? $this->createStub(OtpRepositoryPort::class),
      userRepository: $userRepository ?? $this->createStub(UserRepositoryPort::class),
      eventBus: $eventBus ?? $this->createStub(EventBusPort::class),
      eventIdProvider: new TestEventIdProvider(),
      tokenService: $tokenService ?? $this->createStub(JwtTokenServicePort::class),
      sessionTracking: $sessionTracking ?? $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }

  private function makeCommand(string $code): ConfirmRegistrationCommand
  {
    return new ConfirmRegistrationCommand(
      token: self::TOKEN,
      code: $code,
      ipAddress: self::IP_ADDRESS,
      userAgent: self::USER_AGENT,
    );
  }

  private function makeOtp(OtpPurpose $purpose = OtpPurpose::EMAIL_VERIFICATION): Otp
  {
    return Otp::generate(
      id: new OtpId(self::OTP_ID),
      userId: self::USER_ID,
      purpose: $purpose,
      channel: OtpChannel::EMAIL,
      recipient: self::EMAIL,
    );
  }

  private function reconstitutedOtp(DateTimeImmutable $expiresAt, int $maxAttempts, int $attempts): Otp
  {
    return Otp::reconstitute(
      id: new OtpId(self::OTP_ID),
      challengeToken: ChallengeToken::fromString(self::TOKEN),
      userId: self::USER_ID,
      purpose: OtpPurpose::EMAIL_VERIFICATION,
      channel: OtpChannel::EMAIL,
      codeHash: self::UNUSED_CODE_HASH,
      recipient: self::EMAIL,
      expiresAt: $expiresAt,
      maxAttempts: $maxAttempts,
      attempts: $attempts,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('-2 hours'),
    );
  }

  private function makeUser(): User
  {
    return UserTestFactory::createPending(id: self::USER_ID, email: self::EMAIL);
  }

  /**
   * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, access_token_id: string, refresh_token_id: string}
   */
  private function defaultTokens(): array
  {
    return [
      'access_token' => self::ACCESS_TOKEN,
      'refresh_token' => self::REFRESH_TOKEN,
      'token_type' => 'Bearer',
      'expires_in' => 3600,
      'access_token_id' => self::ACCESS_TOKEN_ID,
      'refresh_token_id' => self::REFRESH_TOKEN_ID,
    ];
  }

  /**
   * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
   */
  private function tokensWithoutIds(): array
  {
    return [
      'access_token' => self::ACCESS_TOKEN,
      'refresh_token' => self::REFRESH_TOKEN,
      'token_type' => 'Bearer',
      'expires_in' => 3600,
    ];
  }
  // #endregion
}
