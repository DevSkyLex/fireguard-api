<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Auth;

use Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenResult as AuthRefreshTokenResult;
use OAuth\Application\UseCase\Query\Token\RefreshToken\{RefreshTokenQuery, RefreshTokenResult as OAuthRefreshTokenResult};
use OAuth\Infrastructure\Adapter\Auth\TokenRefreshAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;

/**
 * Test TokenRefreshAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: TokenRefreshAdapter::class)]
final class TokenRefreshAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testRefreshReturnsSuccessResult(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(RefreshTokenQuery::class))
      ->willReturn(new OAuthRefreshTokenResult(
        success: true,
        userId: 'user-1',
        accessToken: 'access',
        refreshToken: 'refresh',
        tokenType: 'Bearer',
        expiresIn: 3600,
        scopes: ['OPENID'],
        accessTokenId: 'access-id',
        refreshTokenId: 'refresh-id',
        rememberMe: true,
      ));

    $adapter = new TokenRefreshAdapter($queryBus);

    $result = $adapter->refresh('refresh-token', '127.0.0.1');

    self::assertInstanceOf(AuthRefreshTokenResult::class, $result);
    self::assertTrue($result->success);
    self::assertSame('user-1', $result->userId);
    self::assertSame(['OPENID'], $result->scopes);
    self::assertSame('access-id', $result->accessTokenId);
    self::assertSame('refresh-id', $result->refreshTokenId);
    self::assertTrue($result->rememberMe);
  }

  #[Test]
  public function testRefreshReturnsFailedResultWhenQueryFails(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new class ('fail') extends RuntimeException implements Throwable {
      });

    $adapter = new TokenRefreshAdapter($queryBus);

    $result = $adapter->refresh('refresh-token');

    self::assertFalse($result->success);
  }

  #[Test]
  public function testRefreshReturnsFailedResultWhenNotSuccessful(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(OAuthRefreshTokenResult::failed('invalid'));

    $adapter = new TokenRefreshAdapter($queryBus);

    $result = $adapter->refresh('refresh-token');

    self::assertFalse($result->success);
    self::assertSame('invalid', $result->errorMessage);
  }
  // #endregion
}
