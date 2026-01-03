<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Token\IntrospectToken;

use DateTimeImmutable;
use OAuth\Application\Port\Outbound\Token\AccessTokenRepositoryPort;
use OAuth\Application\Port\Outbound\Token\JwtParserPort;
use OAuth\Application\Port\Outbound\Token\RefreshTokenRepositoryPort;
use OAuth\Application\Port\Outbound\Token\TokenCachePort;
use OAuth\Application\UseCase\Query\Token\IntrospectToken\{IntrospectTokenHandler, IntrospectTokenQuery};
use OAuth\Domain\Model\Token\RefreshToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test IntrospectTokenHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: IntrospectTokenHandler::class)]
final class IntrospectTokenHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testIntrospectAccessTokenReturnsInactiveWhenValidationFails.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenReturnsInactiveWhenValidationFails(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('access-token')
      ->willReturn(false);
    $jwtParser->expects(self::never())
      ->method('parse');

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())->method('find');

    $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $refreshTokenRepository->expects(self::never())->method('findByEncryptedToken');
    $refreshTokenRepository->expects(self::never())->method('find');

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())->method('get');

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $refreshTokenRepository,
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertFalse($result->active);
  }

  /**
   * Method testIntrospectRefreshTokenUsesEncryptedLookup.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectRefreshTokenUsesEncryptedLookup(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::never())->method('validate');
    $jwtParser->expects(self::never())->method('parse');

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())->method('find');

    $refreshToken = new RefreshToken(
      identifier: 'refresh-id',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      accessTokenIdentifier: 'access-id',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
    );

    $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $refreshTokenRepository->expects(self::once())
      ->method('findByEncryptedToken')
      ->with('refresh-token')
      ->willReturn($refreshToken);
    $refreshTokenRepository->expects(self::never())
      ->method('find');

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())->method('get');

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $refreshTokenRepository,
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(
      token: 'refresh-token',
      tokenTypeHint: 'refresh_token',
    ));

    self::assertTrue($result->active);
    self::assertSame('refresh_token', $result->tokenType);
    self::assertSame($refreshToken->expiryDateTime()->getTimestamp(), $result->exp);
  }
  // #endregion
}
