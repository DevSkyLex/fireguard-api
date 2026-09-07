<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Session\Login;

use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort, TrustedDeviceCheckPort, UserAuthenticationPort};
use Auth\Application\Port\Outbound\Mfa\{ChallengeGeneratorPort, TotpEnrollmentCheckPort};
use Auth\Application\Service\Federation\SessionIssuer;
use Auth\Application\UseCase\Command\Mfa\MfaChallenge\{MfaChallengeCommand, MfaChallengeResult};
use Auth\Application\UseCase\Command\Session\Login\{LoginCommand, LoginHandler, LoginResult};
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use Auth\Domain\Event\Token\TokenIssuedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, RateLimiterPort};
use Shared\Domain\ValueObject\RateLimitResult;
use User\Application\Port\Inbound\FederatedUserPort;

/**
 * Test LoginHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: LoginHandler::class)]
final class LoginHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeReturnsFailedWhenRateLimited.
   */
  #[Test]
  public function testInvokeReturnsFailedWhenRateLimited(): void
  {
    $command = new LoginCommand(email: 'user@example.com', password: 'secret', ipAddress: '127.0.0.1');

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::rejected(30));

    /** @var EventDispatcherPort&MockObject $dispatcher */
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(LoginFailedEvent::class));

    $handler = $this->handler(
      userAuthentication: $this->createStub(UserAuthenticationPort::class),
      tokenService: $this->createStub(JwtTokenServicePort::class),
      challengeGenerator: $this->createStub(ChallengeGeneratorPort::class),
      sessionTracking: $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $this->createStub(TrustedDeviceCheckPort::class),
      totpEnrollmentCheck: $this->createStub(TotpEnrollmentCheckPort::class),
      mfaEnabled: false,
    );

    $result = $handler->__invoke($command);

    $this->assertInstanceOf(LoginResult::class, $result);
    $this->assertFalse($result->authenticated);
    $this->assertStringContainsString('Too many login attempts', $result->errorMessage ?? '');
    $this->assertSame(LoginResult::ERROR_RATE_LIMIT, $result->errorCode);
    $this->assertSame(30, $result->rateLimitRetryAfter);
  }

  /**
   * Method testInvokeReturnsFailedWhenCredentialsInvalid.
   */
  #[Test]
  public function testInvokeReturnsFailedWhenCredentialsInvalid(): void
  {
    $command = new LoginCommand(email: 'user@example.com', password: 'secret');

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willReturn(UserAuthenticationResult::failed());

    /** @var EventDispatcherPort&MockObject $dispatcher */
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(LoginFailedEvent::class));

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $this->createStub(JwtTokenServicePort::class),
      challengeGenerator: $this->createStub(ChallengeGeneratorPort::class),
      sessionTracking: $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $this->createStub(TrustedDeviceCheckPort::class),
      totpEnrollmentCheck: $this->createStub(TotpEnrollmentCheckPort::class),
      mfaEnabled: false,
    );

    $result = $handler->__invoke($command);

    $this->assertFalse($result->authenticated);
    $this->assertSame('Invalid credentials', $result->errorMessage);
  }

  /**
   * Method testInvokeReturnsMfaChallengeWhenEnabled.
   */
  #[Test]
  public function testInvokeReturnsMfaChallengeWhenEnabled(): void
  {
    $command = new LoginCommand(email: 'user@example.com', password: 'secret');

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willReturn(UserAuthenticationResult::success('user-123', 'user@example.com'));

    $challengeResult = new MfaChallengeResult(
      challengeToken: 'challenge-123',
      maskedRecipient: 'u***@example.com',
      expiresAt: new DateTimeImmutable('+5 minutes'),
      maxAttempts: 3,
    );

    /** @var ChallengeGeneratorPort&MockObject $generator */
    $generator = $this->createMock(ChallengeGeneratorPort::class);
    $generator->expects(self::once())
      ->method('generate')
      ->willReturn($challengeResult);

    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('generatePreAuthToken')
      ->willReturn('pre-auth');
    $jwt->expects(self::never())->method('generateTokens');

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $generator,
      sessionTracking: $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $this->createStub(TrustedDeviceCheckPort::class),
      totpEnrollmentCheck: $this->createStub(TotpEnrollmentCheckPort::class),
      mfaEnabled: true,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->authenticated);
    $this->assertTrue($result->mfaRequired);
    $this->assertSame('pre-auth', $result->mfaToken);
    $this->assertSame('challenge-123', $result->challengeToken);
    $this->assertSame('email', $result->mfaMethod);
  }

  /**
   * Method testInvokeUsesTotpChannelWhenUserHasActiveEnrollment.
   */
  #[Test]
  public function testInvokeUsesTotpChannelWhenUserHasActiveEnrollment(): void
  {
    $command = new LoginCommand(email: 'user@example.com', password: 'secret');

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willReturn(UserAuthenticationResult::success('user-123', 'user@example.com'));

    $challengeResult = new MfaChallengeResult(
      challengeToken: 'challenge-totp',
      maskedRecipient: 'Authenticator App',
      expiresAt: new DateTimeImmutable('+5 minutes'),
      maxAttempts: 5,
    );

    /** @var ChallengeGeneratorPort&MockObject $generator */
    $generator = $this->createMock(ChallengeGeneratorPort::class);
    $generator->expects(self::once())
      ->method('generate')
      ->with(self::callback(static fn (MfaChallengeCommand $cmd): bool => 'totp' === $cmd->channel))
      ->willReturn($challengeResult);

    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('generatePreAuthToken')
      ->willReturn('pre-auth-totp');

    /** @var TotpEnrollmentCheckPort&MockObject $totpEnrollmentCheck */
    $totpEnrollmentCheck = $this->createMock(TotpEnrollmentCheckPort::class);
    $totpEnrollmentCheck->expects(self::once())
      ->method('isEnrolled')
      ->with('user-123')
      ->willReturn(true);

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $generator,
      sessionTracking: $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $this->createStub(TrustedDeviceCheckPort::class),
      totpEnrollmentCheck: $totpEnrollmentCheck,
      mfaEnabled: true,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->mfaRequired);
    $this->assertSame('totp', $result->mfaMethod);
    $this->assertSame('Authenticator App', $result->mfaDestination);
  }

  /**
   * Method testInvokeGeneratesTokensOnSuccess.
   */
  #[Test]
  public function testInvokeGeneratesTokensOnSuccess(): void
  {
    $command = new LoginCommand(
      email: 'user@example.com',
      password: 'secret',
      rememberMe: true,
      ipAddress: '127.0.0.1',
      userAgent: 'Agent',
    );

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willReturn(UserAuthenticationResult::success('user-123', 'user@example.com'));

    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('generateTokens')
      ->willReturn([
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'access_token_id' => 'access-id',
        'refresh_token_id' => 'refresh-id',
      ]);
    $jwt->expects(self::never())->method('decodeRefreshToken');

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('recordSession')
      ->with(
        'user-123',
        '127.0.0.1',
        'Agent',
        'access-id',
        'refresh-id',
        true,
      );

    /** @var EventDispatcherPort&MockObject $dispatcher */
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $callIndex = 0;
    $dispatcher->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function (object $event) use (&$callIndex): void {
        if (0 === $callIndex) {
          self::assertInstanceOf(UserLoggedInEvent::class, $event);
        } else {
          self::assertInstanceOf(TokenIssuedEvent::class, $event);
        }
        ++$callIndex;
      });

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $this->createStub(ChallengeGeneratorPort::class),
      sessionTracking: $sessionTracking,
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $this->createStub(TrustedDeviceCheckPort::class),
      totpEnrollmentCheck: $this->createStub(TotpEnrollmentCheckPort::class),
      mfaEnabled: false,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->authenticated);
    $this->assertSame('access', $result->accessToken);
    $this->assertSame('refresh', $result->refreshToken);
  }

  #[Test]
  public function testInvokeReturnsFailedWhenAuthenticationThrows(): void
  {
    $command = new LoginCommand(email: 'user@example.com', password: 'secret', ipAddress: '127.0.0.1');

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willThrowException(new RuntimeException('boom'));

    /** @var LoggerInterface&MockObject $logger */
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('critical')
      ->with('Password authentication failed unexpectedly.', [
        'exception_class' => RuntimeException::class,
      ]);

    /** @var EventDispatcherPort&MockObject $dispatcher */
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(LoginFailedEvent::class));

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $this->createStub(JwtTokenServicePort::class),
      challengeGenerator: $this->createStub(ChallengeGeneratorPort::class),
      sessionTracking: $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $this->createStub(TrustedDeviceCheckPort::class),
      totpEnrollmentCheck: $this->createStub(TotpEnrollmentCheckPort::class),
      mfaEnabled: false,
      logger: $logger,
    );

    $result = $handler->__invoke($command);

    $this->assertFalse($result->authenticated);
  }

  #[Test]
  public function testInvokeSucceedsWhenSessionTrackingFails(): void
  {
    $command = new LoginCommand(
      email: 'user@example.com',
      password: 'secret',
      ipAddress: '127.0.0.1',
      userAgent: 'Agent',
    );

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willReturn(UserAuthenticationResult::success('user-123', 'user@example.com'));

    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('generateTokens')
      ->willReturn([
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
      ]);
    $jwt->expects(self::once())
      ->method('decodeRefreshToken')
      ->willReturn([
        'access_token_id' => 'access-id',
        'refresh_token_id' => 'refresh-id',
      ]);

    $methodRecorded = false;
    /** @var FederatedUserPort&MockObject $users */
    $users = $this->createMock(FederatedUserPort::class);
    $users->expects(self::once())
      ->method('recordSignInMethod')
      ->with('user-123', 'password')
      ->willReturnCallback(static function () use (&$methodRecorded): bool {
        $methodRecorded = true;

        return true;
      });

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('recordSession')
      ->willReturnCallback(static function () use (&$methodRecorded): never {
        self::assertTrue($methodRecorded, 'The sign-in method must be persisted before best-effort tracking.');

        throw new RuntimeException('tracking failed');
      });

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $this->createStub(ChallengeGeneratorPort::class),
      sessionTracking: $sessionTracking,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $this->createStub(TrustedDeviceCheckPort::class),
      totpEnrollmentCheck: $this->createStub(TotpEnrollmentCheckPort::class),
      mfaEnabled: false,
      users: $users,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->authenticated);
    $this->assertSame('access', $result->accessToken);
  }

  #[Test]
  public function testInvokeSkipsMfaForATrustedDevice(): void
  {
    $command = new LoginCommand(
      email: 'user@example.com',
      password: 'secret',
      trustedDeviceToken: 'trusted-device-token',
    );

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willReturn(UserAuthenticationResult::success('user-123', 'user@example.com'));

    /** @var TrustedDeviceCheckPort&MockObject $trustedDeviceCheck */
    $trustedDeviceCheck = $this->createMock(TrustedDeviceCheckPort::class);
    $trustedDeviceCheck->expects(self::once())
      ->method('isTrusted')
      ->with('user-123', 'trusted-device-token')
      ->willReturn(true);

    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::never())->method('generatePreAuthToken');
    $jwt->expects(self::once())
      ->method('generateTokens')
      ->willReturn([
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'access_token_id' => 'access-id',
        'refresh_token_id' => 'refresh-id',
      ]);

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $this->createStub(ChallengeGeneratorPort::class),
      sessionTracking: $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $trustedDeviceCheck,
      totpEnrollmentCheck: $this->createStub(TotpEnrollmentCheckPort::class),
      mfaEnabled: true,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->authenticated);
    $this->assertNotTrue($result->mfaRequired);
    $this->assertSame('access', $result->accessToken);
  }

  #[Test]
  public function testInvokeRequiresMfaWhenTrustedDeviceAndTotpChecksFail(): void
  {
    $command = new LoginCommand(
      email: 'user@example.com',
      password: 'secret',
      trustedDeviceToken: 'trusted-device-token',
    );

    /** @var RateLimiterPort&MockObject $rateLimiter */
    $rateLimiter = $this->createMock(RateLimiterPort::class);
    $rateLimiter->expects(self::once())
      ->method('consume')
      ->willReturn(RateLimitResult::accepted());

    /** @var UserAuthenticationPort&MockObject $auth */
    $auth = $this->createMock(UserAuthenticationPort::class);
    $auth->expects(self::once())
      ->method('authenticate')
      ->willReturn(UserAuthenticationResult::success('user-123', 'user@example.com'));

    /** @var TrustedDeviceCheckPort&MockObject $trustedDeviceCheck */
    $trustedDeviceCheck = $this->createMock(TrustedDeviceCheckPort::class);
    $trustedDeviceCheck->expects(self::once())
      ->method('isTrusted')
      ->willThrowException(new RuntimeException('trusted device store unavailable'));

    /** @var TotpEnrollmentCheckPort&MockObject $totpEnrollmentCheck */
    $totpEnrollmentCheck = $this->createMock(TotpEnrollmentCheckPort::class);
    $totpEnrollmentCheck->expects(self::once())
      ->method('isEnrolled')
      ->willThrowException(new RuntimeException('totp store unavailable'));

    /** @var ChallengeGeneratorPort&MockObject $generator */
    $generator = $this->createMock(ChallengeGeneratorPort::class);
    $generator->expects(self::once())
      ->method('generate')
      ->with(self::callback(static fn (MfaChallengeCommand $cmd): bool => 'email' === $cmd->channel))
      ->willReturn(new MfaChallengeResult(
        challengeToken: 'challenge-123',
        maskedRecipient: 'u***@example.com',
        expiresAt: new DateTimeImmutable('+5 minutes'),
        maxAttempts: 3,
      ));

    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('generatePreAuthToken')
      ->willReturn('pre-auth');
    $jwt->expects(self::never())->method('generateTokens');

    $handler = $this->handler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $generator,
      sessionTracking: $this->createStub(SessionTrackingPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      rateLimiter: $rateLimiter,
      trustedDeviceCheck: $trustedDeviceCheck,
      totpEnrollmentCheck: $totpEnrollmentCheck,
      mfaEnabled: true,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->mfaRequired);
    $this->assertSame('email', $result->mfaMethod);
  }

  /**
   * Preserves the pre-extraction test setup while constructing the shared issuer.
   *
   * @since 1.0.0
   */
  private function handler(
    UserAuthenticationPort $userAuthentication,
    JwtTokenServicePort $tokenService,
    ChallengeGeneratorPort $challengeGenerator,
    SessionTrackingPort $sessionTracking,
    EventDispatcherPort $eventDispatcher,
    RateLimiterPort $rateLimiter,
    TrustedDeviceCheckPort $trustedDeviceCheck,
    TotpEnrollmentCheckPort $totpEnrollmentCheck,
    bool $mfaEnabled,
    ?LoggerInterface $logger = null,
    ?FederatedUserPort $users = null,
  ): LoginHandler {
    return new LoginHandler(
      userAuthentication: $userAuthentication,
      eventDispatcher: $eventDispatcher,
      rateLimiter: $rateLimiter,
      sessionIssuer: new SessionIssuer(
        tokenService: $tokenService,
        challengeGenerator: $challengeGenerator,
        sessionTracking: $sessionTracking,
        eventDispatcher: $eventDispatcher,
        trustedDeviceCheck: $trustedDeviceCheck,
        totpEnrollmentCheck: $totpEnrollmentCheck,
        users: $users ?? $this->createStub(FederatedUserPort::class),
        mfaEnabled: $mfaEnabled,
      ),
      logger: $logger ?? $this->createStub(LoggerInterface::class),
    );
  }
  // #endregion
}
