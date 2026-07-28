<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Token\IntrospectToken;

use DateTimeImmutable;
use OAuth\Application\Port\Outbound\Token\{AccessTokenRepositoryPort, JwtParserPort, RefreshTokenRepositoryPort, TokenCachePort};
use OAuth\Application\UseCase\Query\Token\IntrospectToken\{IntrospectTokenHandler, IntrospectTokenQuery};
use OAuth\Domain\Model\Token\{AccessToken, RefreshToken};
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function time;

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
    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())->method('get');

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
   * Method testIntrospectAccessTokenReturnsInactiveWhenTokenMissing.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenReturnsInactiveWhenTokenMissing(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::never())->method('validate');
    $jwtParser->expects(self::never())->method('parse');

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())->method('get');

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: ''));

    self::assertFalse($result->active);
  }

  /**
   * Method testIntrospectAccessTokenReturnsInactiveWhenParseFails.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenReturnsInactiveWhenParseFails(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('access-token')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->with('access-token')
      ->willReturn(null);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())->method('get');

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertFalse($result->active);
  }

  /**
   * Method testIntrospectAccessTokenUsesCache.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenTreatsAnExpiredCacheEntryAsInactive(): void
  {
    // A cached entry outlives the token it describes: the handler must re-check
    // `exp` rather than trust that a cache hit means the token is still live.
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())->method('validate')->willReturn(true);
    $jwtParser->expects(self::once())->method('parse')->willReturn(['jti' => 'token-id']);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::once())
      ->method('get')
      ->with('token-id')
      ->willReturn([
        'active' => true,
        'scope' => 'openid',
        'exp' => time() - 60,
      ]);

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertFalse($result->active);
  }

  #[Test]
  public function testIntrospectAccessTokenReturnsInactiveWhenTokenIdIsMissing(): void
  {
    // Without a `jti` there is nothing to look up, cached or otherwise.
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())->method('validate')->willReturn(true);
    $jwtParser->expects(self::once())->method('parse')->willReturn(['aud' => ['client-1']]);

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $this->createStub(TokenCachePort::class),
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertFalse($result->active);
  }

  #[Test]
  public function testIntrospectAccessTokenUsesCache(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('access-token')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->with('access-token')
      ->willReturn([
        'jti' => 'token-id',
        'aud' => ['client-1', 'client-2'],
      ]);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::once())
      ->method('get')
      ->with('token-id')
      ->willReturn([
        'active' => true,
        'scope' => 'openid',
        'client_id' => 'client-1',
        'token_type' => 'Bearer',
        'exp' => time() + 300,
        'iat' => time(),
        'nbf' => time(),
        'sub' => 'user-123',
        'aud' => 'client-1 client-2',
        'iss' => 'https://issuer.example',
        'jti' => 'token-id',
      ]);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())->method('find');

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertTrue($result->active);
    self::assertSame('client-1 client-2', $result->aud);
    self::assertSame('token-id', $result->jti);
  }

  /**
   * Method testIntrospectAccessTokenCachesResult.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenCachesResult(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('access-token')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->with('access-token')
      ->willReturn([
        'jti' => 'token-id',
        'aud' => 'client-1',
        'iat' => time(),
        'nbf' => time(),
      ]);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::once())
      ->method('get')
      ->with('token-id')
      ->willReturn(null);
    $tokenCache->expects(self::once())
      ->method('set')
      ->with(self::equalTo('token-id'), self::isArray(), self::greaterThan(0));

    $accessToken = new AccessToken(
      identifier: 'token-id',
      clientIdentifier: new OAuthClientIdentifier('client-1'),
      expiry: new DateTimeImmutable('+10 minutes'),
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: 'user-123',
    );

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('token-id')
      ->willReturn($accessToken);

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertTrue($result->active);
    self::assertSame('user-123', $result->sub);
    self::assertSame('https://issuer.example', $result->iss);
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

  /**
   * Method testIntrospectRefreshTokenFallsBackToFind.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectRefreshTokenFallsBackToFind(): void
  {
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
      ->willReturn(null);
    $refreshTokenRepository->expects(self::once())
      ->method('find')
      ->with('refresh-token')
      ->willReturn($refreshToken);

    $handler = new IntrospectTokenHandler(
      jwtParser: $this->createStub(JwtParserPort::class),
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $refreshTokenRepository,
      tokenCache: $this->createStub(TokenCachePort::class),
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(
      token: 'refresh-token',
      tokenTypeHint: 'refresh_token',
    ));

    self::assertTrue($result->active);
    self::assertSame('refresh_token', $result->tokenType);
  }

  /**
   * Method testIntrospectRefreshTokenReturnsInactiveWhenRevoked.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectRefreshTokenReturnsInactiveWhenRevoked(): void
  {
    $refreshToken = new RefreshToken(
      identifier: 'refresh-id',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      accessTokenIdentifier: 'access-id',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      isRevoked: true,
    );

    $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $refreshTokenRepository->expects(self::once())
      ->method('findByEncryptedToken')
      ->with('refresh-token')
      ->willReturn($refreshToken);
    $refreshTokenRepository->expects(self::never())->method('find');

    $handler = new IntrospectTokenHandler(
      jwtParser: $this->createStub(JwtParserPort::class),
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $refreshTokenRepository,
      tokenCache: $this->createStub(TokenCachePort::class),
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(
      token: 'refresh-token',
      tokenTypeHint: 'refresh_token',
    ));

    self::assertFalse($result->active);
  }

  /**
   * Method testIntrospectAccessTokenReturnsInactiveWhenTheJtiClaimIsNotAString.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenReturnsInactiveWhenTheJtiClaimIsNotAString(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->with('access-token')
      ->willReturn(true);
    $jwtParser->expects(self::once())
      ->method('parse')
      ->with('access-token')
      ->willReturn(['jti' => 42, 'sub' => 'user-123']);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())->method('get');

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())->method('find');

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertFalse($result->active);
  }

  /**
   * Method testIntrospectAccessTokenReturnsInactiveWhenTheJtiClaimIsAbsent.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenReturnsInactiveWhenTheJtiClaimIsAbsent(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())->method('validate')->willReturn(true);
    $jwtParser->expects(self::once())->method('parse')->willReturn(['sub' => 'user-123']);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())->method('get');

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    self::assertFalse($handler->__invoke(new IntrospectTokenQuery(token: 'access-token'))->active);
  }

  /**
   * Method testIntrospectAccessTokenReturnsInactiveWhenTheStoredTokenIsGone.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectAccessTokenReturnsInactiveWhenTheStoredTokenIsGone(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())->method('validate')->willReturn(true);
    $jwtParser->expects(self::once())->method('parse')->willReturn([
      'jti' => 'token-123',
      'sub' => 'user-123',
      'aud' => 'client-123',
    ]);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::once())
      ->method('get')
      ->with('token-123')
      ->willReturn(null);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn(null);

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      issuer: 'https://issuer.example',
    );

    // A signature-valid JWT whose row was deleted must fail closed.
    self::assertFalse($handler->__invoke(new IntrospectTokenQuery(token: 'access-token'))->active);
  }

  /**
   * Method testIntrospectRefreshTokenReturnsInactiveWhenExpired.
   *
   * @return void no return value
   */
  #[Test]
  public function testIntrospectRefreshTokenReturnsInactiveWhenExpired(): void
  {
    $refreshToken = new RefreshToken(
      identifier: 'refresh-id',
      expiryDateTime: new DateTimeImmutable('-1 hour'),
      accessTokenIdentifier: 'access-id',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
    );

    $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $refreshTokenRepository->expects(self::once())
      ->method('findByEncryptedToken')
      ->with('refresh-token')
      ->willReturn($refreshToken);
    $refreshTokenRepository->expects(self::never())->method('find');

    $handler = new IntrospectTokenHandler(
      jwtParser: $this->createStub(JwtParserPort::class),
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $refreshTokenRepository,
      tokenCache: $this->createStub(TokenCachePort::class),
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(
      token: 'refresh-token',
      tokenTypeHint: 'refresh_token',
    ));

    self::assertFalse($result->active);
  }

  /**
   * Method testInvokeReturnsInactiveOnException.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvokeReturnsInactiveOnException(): void
  {
    $jwtParser = $this->createMock(JwtParserPort::class);
    $jwtParser->expects(self::once())
      ->method('validate')
      ->willThrowException(new RuntimeException('boom'));

    $handler = new IntrospectTokenHandler(
      jwtParser: $jwtParser,
      accessTokenRepository: $this->createStub(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createStub(RefreshTokenRepositoryPort::class),
      tokenCache: $this->createStub(TokenCachePort::class),
      issuer: 'https://issuer.example',
    );

    $result = $handler->__invoke(new IntrospectTokenQuery(token: 'access-token'));

    self::assertFalse($result->active);
  }
  // #endregion
}
