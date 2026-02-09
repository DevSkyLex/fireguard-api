<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Query\Session\RefreshToken;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort, TokenRefreshPort};
use Auth\Application\UseCase\Query\Session\RefreshToken\{RefreshTokenHandler, RefreshTokenQuery, RefreshTokenResult};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
  /**
   * Method testInvokeRotatesSessionTokensOnSuccess.
   */
  #[Test]
  public function testInvokeRotatesSessionTokensOnSuccess(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::exactly(2))
      ->method('decodeRefreshToken')
      ->willReturnOnConsecutiveCalls(
        ['refresh_token_id' => 'current-refresh', 'access_token_id' => 'current-access'],
        ['refresh_token_id' => 'new-refresh', 'access_token_id' => 'new-access'],
      );

    $result = new RefreshTokenResult(
      success: true,
      accessToken: 'access',
      refreshToken: 'new-refresh-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['READ'],
    );

    /** @var TokenRefreshPort&MockObject $refresh */
    $refresh = $this->createMock(TokenRefreshPort::class);
    $refresh->expects(self::once())
      ->method('refresh')
      ->with('refresh-token', '127.0.0.1')
      ->willReturn($result);

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('rotateSessionTokens')
      ->with('current-refresh', 'current-access', 'new-access', 'new-refresh');

    $handler = new RefreshTokenHandler(
      tokenRefresh: $refresh,
      jwtService: $jwt,
      sessionTracking: $sessionTracking,
    );

    $query = new RefreshTokenQuery(refreshToken: 'refresh-token', ipAddress: '127.0.0.1');

    $this->assertSame($result, $handler->__invoke($query));
  }

  /**
   * Method testInvokeSkipsRotationWhenCurrentMissing.
   */
  #[Test]
  public function testInvokeSkipsRotationWhenCurrentMissing(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::once())
      ->method('decodeRefreshToken')
      ->willReturn(null);

    $result = new RefreshTokenResult(
      success: true,
      accessToken: 'access',
      refreshToken: 'new-refresh',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['READ'],
    );

    /** @var TokenRefreshPort&MockObject $refresh */
    $refresh = $this->createMock(TokenRefreshPort::class);
    $refresh->expects(self::once())
      ->method('refresh')
      ->willReturn($result);

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::never())->method('rotateSessionTokens');

    $handler = new RefreshTokenHandler(
      tokenRefresh: $refresh,
      jwtService: $jwt,
      sessionTracking: $sessionTracking,
    );

    $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token'));
  }

  #[Test]
  public function testInvokeSkipsRotationWhenNewPayloadInvalid(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::exactly(2))
      ->method('decodeRefreshToken')
      ->willReturnOnConsecutiveCalls(
        ['refresh_token_id' => 'current-refresh', 'access_token_id' => 'current-access'],
        null,
      );

    $result = new RefreshTokenResult(
      success: true,
      accessToken: 'access',
      refreshToken: 'new-refresh-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['READ'],
    );

    /** @var TokenRefreshPort&MockObject $refresh */
    $refresh = $this->createMock(TokenRefreshPort::class);
    $refresh->expects(self::once())
      ->method('refresh')
      ->willReturn($result);

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::never())->method('rotateSessionTokens');

    $handler = new RefreshTokenHandler(
      tokenRefresh: $refresh,
      jwtService: $jwt,
      sessionTracking: $sessionTracking,
    );

    self::assertSame($result, $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token')));
  }

  #[Test]
  public function testInvokeSkipsRotationWhenNewTokenIdsMissing(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::exactly(2))
      ->method('decodeRefreshToken')
      ->willReturnOnConsecutiveCalls(
        ['refresh_token_id' => 'current-refresh', 'access_token_id' => 'current-access'],
        ['refresh_token_id' => '', 'access_token_id' => ''],
      );

    $result = new RefreshTokenResult(
      success: true,
      accessToken: 'access',
      refreshToken: 'new-refresh-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['READ'],
    );

    /** @var TokenRefreshPort&MockObject $refresh */
    $refresh = $this->createMock(TokenRefreshPort::class);
    $refresh->expects(self::once())
      ->method('refresh')
      ->willReturn($result);

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::never())->method('rotateSessionTokens');

    $handler = new RefreshTokenHandler(
      tokenRefresh: $refresh,
      jwtService: $jwt,
      sessionTracking: $sessionTracking,
    );

    self::assertSame($result, $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token')));
  }

  #[Test]
  public function testInvokeIgnoresSessionTrackingFailures(): void
  {
    /** @var JwtTokenServicePort&MockObject $jwt */
    $jwt = $this->createMock(JwtTokenServicePort::class);
    $jwt->expects(self::exactly(2))
      ->method('decodeRefreshToken')
      ->willReturnOnConsecutiveCalls(
        ['refresh_token_id' => 'current-refresh', 'access_token_id' => 'current-access'],
        ['refresh_token_id' => 'new-refresh', 'access_token_id' => 'new-access'],
      );

    $result = new RefreshTokenResult(
      success: true,
      accessToken: 'access',
      refreshToken: 'new-refresh-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['READ'],
    );

    /** @var TokenRefreshPort&MockObject $refresh */
    $refresh = $this->createMock(TokenRefreshPort::class);
    $refresh->expects(self::once())
      ->method('refresh')
      ->willReturn($result);

    /** @var SessionTrackingPort&MockObject $sessionTracking */
    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('rotateSessionTokens')
      ->willThrowException(new RuntimeException('boom'));

    $handler = new RefreshTokenHandler(
      tokenRefresh: $refresh,
      jwtService: $jwt,
      sessionTracking: $sessionTracking,
    );

    self::assertSame($result, $handler->__invoke(new RefreshTokenQuery(refreshToken: 'refresh-token')));
  }
  // #endregion
}
