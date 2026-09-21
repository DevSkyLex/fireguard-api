<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Query\Session\RefreshToken;

use Auth\Application\Port\Outbound\TokenRefreshPort;
use Auth\Application\UseCase\Query\Session\RefreshToken\{RefreshTokenHandler, RefreshTokenQuery, RefreshTokenResult};
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RefreshTokenHandlerTest extends TestCase
{
  public function testPreservesTheIssuersFailureWithoutReturningAnyTokens(): void
  {
    $issuer = $this->createMock(TokenRefreshPort::class);
    $issuer->expects(self::once())->method('refresh')->with('replayed', '127.0.0.1')->willReturn(RefreshTokenResult::failed());
    $result = new RefreshTokenHandler($issuer)(new RefreshTokenQuery('replayed', '127.0.0.1'));
    self::assertFalse($result->success);
    self::assertNull($result->accessToken);
    self::assertNull($result->refreshToken);
  }

  public function testTechnicalFailureIsNotConvertedToSuccess(): void
  {
    $issuer = $this->createStub(TokenRefreshPort::class);
    $issuer->method('refresh')->willThrowException(new RuntimeException('Session storage unavailable'));
    $this->expectException(RuntimeException::class);
    new RefreshTokenHandler($issuer)(new RefreshTokenQuery('token'));
  }
}
