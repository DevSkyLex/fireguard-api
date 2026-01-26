<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Session\Login;

use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort, UserAuthenticationPort};
use Auth\Application\Port\Outbound\Mfa\ChallengeGeneratorPort;
use Auth\Application\UseCase\Command\Mfa\MfaChallenge\MfaChallengeResult;
use Auth\Application\UseCase\Command\Session\Login\{LoginCommand, LoginHandler, LoginResult};
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use Auth\Domain\Event\Token\TokenIssuedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, RateLimiterPort};
use Shared\Domain\ValueObject\RateLimitResult;

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

    $handler = new LoginHandler(
      userAuthentication: $this->createMock(UserAuthenticationPort::class),
      tokenService: $this->createMock(JwtTokenServicePort::class),
      challengeGenerator: $this->createMock(ChallengeGeneratorPort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
      mfaEnabled: false,
    );

    $result = $handler->__invoke($command);

    $this->assertInstanceOf(LoginResult::class, $result);
    $this->assertFalse($result->authenticated);
    $this->assertStringContainsString('Too many login attempts', $result->errorMessage ?? '');
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

    $handler = new LoginHandler(
      userAuthentication: $auth,
      tokenService: $this->createMock(JwtTokenServicePort::class),
      challengeGenerator: $this->createMock(ChallengeGeneratorPort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
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

    $handler = new LoginHandler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $generator,
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      rateLimiter: $rateLimiter,
      mfaEnabled: true,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->authenticated);
    $this->assertTrue($result->mfaRequired);
    $this->assertSame('pre-auth', $result->mfaToken);
    $this->assertSame('challenge-123', $result->challengeToken);
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
      ]);
    $jwt->expects(self::once())
      ->method('decodeRefreshToken')
      ->willReturn([
        'access_token_id' => 'access-id',
        'refresh_token_id' => 'refresh-id',
      ]);

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

    $handler = new LoginHandler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $this->createMock(ChallengeGeneratorPort::class),
      sessionTracking: $sessionTracking,
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
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

    /** @var EventDispatcherPort&MockObject $dispatcher */
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(LoginFailedEvent::class));

    $handler = new LoginHandler(
      userAuthentication: $auth,
      tokenService: $this->createMock(JwtTokenServicePort::class),
      challengeGenerator: $this->createMock(ChallengeGeneratorPort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $dispatcher,
      rateLimiter: $rateLimiter,
      mfaEnabled: false,
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

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('recordSession')
      ->willThrowException(new RuntimeException('tracking failed'));

    $handler = new LoginHandler(
      userAuthentication: $auth,
      tokenService: $jwt,
      challengeGenerator: $this->createMock(ChallengeGeneratorPort::class),
      sessionTracking: $sessionTracking,
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      rateLimiter: $rateLimiter,
      mfaEnabled: false,
    );

    $result = $handler->__invoke($command);

    $this->assertTrue($result->authenticated);
    $this->assertSame('access', $result->accessToken);
  }
  // #endregion
}
