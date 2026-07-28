<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Token\RefreshToken;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use DateTimeImmutable;
use OAuth\Application\UseCase\Query\Token\RefreshToken\{RefreshTokenHandler, RefreshTokenQuery};
use OAuth\Domain\Event\Token\{TokenRefreshFailedEvent, TokenRefreshedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

/**
 * Test RefreshTokenHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RefreshTokenHandler::class)]
final class RefreshTokenHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testReturnsFailureWhenTokenMissing(): void
  {
    $handler = new RefreshTokenHandler(
      tokenService: $this->createStub(JwtTokenServicePort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: '', ipAddress: '127.0.0.1'));

    self::assertFalse($result->success);
    self::assertSame('Refresh token is required', $result->errorMessage);
  }

  #[Test]
  public function testReturnsFailureWhenTokenInvalid(): void
  {
    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh-token')
      ->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (TokenRefreshFailedEvent $event): bool => 'invalid_token' === $event->reason
          && null === $event->userId
          && '127.0.0.1' === $event->ipAddress,
      ));

    $handler = new RefreshTokenHandler(
      tokenService: $tokenService,
      queryBus: $this->createStub(QueryBusPort::class),
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1'));

    self::assertFalse($result->success);
    self::assertSame('Invalid or expired refresh token', $result->errorMessage);
  }

  #[Test]
  public function testReturnsFailureWhenUserInactive(): void
  {
    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh-token')
      ->willReturn([
        'user_id' => 'user-123',
        'scopes' => ['openid'],
      ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: $this->createUserView(canLogin: false)));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (TokenRefreshFailedEvent $event): bool => 'user_inactive' === $event->reason
          && 'user-123' === $event->userId,
      ));

    $handler = new RefreshTokenHandler(
      tokenService: $tokenService,
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1'));

    self::assertFalse($result->success);
    self::assertSame('User account is not active', $result->errorMessage);
  }

  #[Test]
  public function testReturnsFailureWhenUserNotFound(): void
  {
    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh-token')
      ->willReturn([
        'user_id' => 'user-404',
        'scopes' => ['openid'],
      ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: null));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (TokenRefreshFailedEvent $event): bool => 'user_not_found' === $event->reason
          && 'user-404' === $event->userId,
      ));

    $handler = new RefreshTokenHandler(
      tokenService: $tokenService,
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1'));

    self::assertFalse($result->success);
    self::assertSame('User not found', $result->errorMessage);
  }

  #[Test]
  public function testReturnsFailureWhenUserIdMissingInToken(): void
  {
    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh-token')
      ->willReturn([
        'user_id' => '',
        'scopes' => ['openid'],
      ]);

    $handler = new RefreshTokenHandler(
      tokenService: $tokenService,
      queryBus: $this->createStub(QueryBusPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1'));

    self::assertFalse($result->success);
    self::assertSame('Invalid refresh token', $result->errorMessage);
  }

  #[Test]
  public function testReturnsFailureWhenUserLookupFails(): void
  {
    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh-token')
      ->willReturn([
        'user_id' => 'user-123',
        'scopes' => ['openid'],
      ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new RuntimeException('boom'));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (TokenRefreshFailedEvent $event): bool => 'verification_error' === $event->reason
          && 'user-123' === $event->userId,
      ));

    $handler = new RefreshTokenHandler(
      tokenService: $tokenService,
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1'));

    self::assertFalse($result->success);
    self::assertSame('Failed to verify user', $result->errorMessage);
  }

  #[Test]
  public function testReturnsSuccessWhenValid(): void
  {
    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh-token')
      ->willReturn([
        'user_id' => 'user-123',
        'scopes' => ['openid', 'profile'],
        'remember_me' => true,
      ]);
    $tokenService->expects(self::once())
      ->method('generateTokens')
      ->with('user-123', '', ['openid', 'profile'], true)
      ->willReturn([
        'access_token' => 'access-token',
        'refresh_token' => 'new-refresh-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'access_token_id' => 'access-id-new',
        'refresh_token_id' => 'refresh-id-new',
        'remember_me' => true,
      ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: $this->createUserView(canLogin: true)));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TokenRefreshedEvent::class));

    $handler = new RefreshTokenHandler(
      tokenService: $tokenService,
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1'));

    self::assertTrue($result->success);
    self::assertSame('access-token', $result->accessToken);
    self::assertSame('new-refresh-token', $result->refreshToken);
    self::assertSame(['openid', 'profile'], $result->scopes);
    self::assertSame('access-id-new', $result->accessTokenId);
    self::assertSame('refresh-id-new', $result->refreshTokenId);
    self::assertTrue($result->rememberMe);
  }

  #[Test]
  public function testTokenIdentifiersAreNullWhenTheServiceOmitsOrBlanksThem(): void
  {
    // The token service is not contractually required to return the optional
    // identifier keys; the handler must degrade to null rather than assume.
    /** @var JwtTokenServicePort&MockObject $tokenService */
    $tokenService = $this->createMock(JwtTokenServicePort::class);
    $tokenService->expects(self::once())
      ->method('decodeRefreshToken')
      ->willReturn([
        'user_id' => 'user-123',
        'scopes' => ['openid'],
        'remember_me' => false,
      ]);
    $tokenService->expects(self::once())
      ->method('generateTokens')
      ->willReturn([
        'access_token' => 'access-token',
        'refresh_token' => 'new-refresh-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        // access_token_id absent entirely, refresh_token_id present but empty.
        'refresh_token_id' => '',
        'remember_me' => false,
      ]);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: $this->createUserView(canLogin: true)));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new RefreshTokenHandler(
      tokenService: $tokenService,
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1'));

    self::assertTrue($result->success);
    self::assertNull($result->accessTokenId);
    self::assertNull($result->refreshTokenId);
  }

  private function createUserView(bool $canLogin): UserView
  {
    return new UserView(
      id: 'user-123',
      username: 'user',
      email: 'user@example.com',
      firstName: 'Test',
      lastName: 'User',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      lastLoginAt: null,
      canLogin: $canLogin,
    );
  }
  // #endregion
}
