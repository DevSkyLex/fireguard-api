<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Token;

use DateTimeImmutable;
use Defuse\Crypto\Crypto;
use OAuth\Application\Port\Outbound\Token\{AccessTokenRepositoryPort, RefreshTokenRepositoryPort, TokenCachePort};
use OAuth\Domain\Model\Token\{AccessToken, RefreshToken};
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Adapter\Token\TokenRevocationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Session\Application\Port\Inbound\Tracking\SessionTrackingPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function base64_encode;
use function implode;
use function json_encode;
use function rtrim;
use function strtr;

/**
 * Test TokenRevocationAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: TokenRevocationAdapter::class)]
final class TokenRevocationAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testRevokeRefreshTokenReturnsFalseOnEmptyToken(): void
  {
    $adapter = $this->createAdapter();

    self::assertFalse($adapter->revokeRefreshToken(''));
  }

  #[Test]
  public function testRevokeRefreshTokenRevokesToken(): void
  {
    $refreshToken = $this->createRefreshToken('refresh-id', 'access-id');
    $encrypted = $this->encryptPayload([
      'refresh_token_id' => 'refresh-id',
      'access_token_id' => 'access-id',
    ]);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::never())
      ->method('find');

    $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $refreshTokenRepository->expects(self::once())
      ->method('find')
      ->with('refresh-id')
      ->willReturn($refreshToken);
    $refreshTokenRepository->expects(self::once())
      ->method('save')
      ->with($refreshToken);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::once())
      ->method('invalidate')
      ->with('refresh-id');

    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('revokeSessionByToken')
      ->with('refresh-id', 'access-id');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info');

    $adapter = new TokenRevocationAdapter(
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $refreshTokenRepository,
      tokenCache: $tokenCache,
      sessionTracking: $sessionTracking,
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $logger,
      encryptionKey: $this->encryptionKey(),
    );

    self::assertTrue($adapter->revokeRefreshToken($encrypted));
  }

  #[Test]
  public function testRevokeRefreshTokenReturnsFalseForInvalidPayload(): void
  {
    $encrypted = $this->encryptPayload([
      'access_token_id' => 'access-id',
    ]);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::never())
      ->method('invalidate');

    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::never())
      ->method('revokeSessionByToken');

    $adapter = new TokenRevocationAdapter(
      accessTokenRepository: $this->createMock(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createMock(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      sessionTracking: $sessionTracking,
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $this->createMock(LoggerInterface::class),
      encryptionKey: $this->encryptionKey(),
    );

    self::assertFalse($adapter->revokeRefreshToken($encrypted));
  }

  #[Test]
  public function testRevokeRefreshTokenReturnsFalseWhenTokenNotFound(): void
  {
    $encrypted = $this->encryptPayload([
      'refresh_token_id' => 'missing-token',
    ]);

    $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $refreshTokenRepository->expects(self::once())
      ->method('find')
      ->with('missing-token')
      ->willReturn(null);

    $adapter = new TokenRevocationAdapter(
      accessTokenRepository: $this->createMock(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $refreshTokenRepository,
      tokenCache: $this->createMock(TokenCachePort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $this->createMock(LoggerInterface::class),
      encryptionKey: $this->encryptionKey(),
    );

    self::assertFalse($adapter->revokeRefreshToken($encrypted));
  }

  #[Test]
  public function testRevokeRefreshTokenReturnsFalseWhenTokenIdNotString(): void
  {
    $encrypted = $this->encryptPayload([
      'refresh_token_id' => 123,
    ]);

    $adapter = $this->createAdapter();

    self::assertFalse($adapter->revokeRefreshToken($encrypted));
  }

  #[Test]
  public function testRevokeRefreshTokenReturnsFalseWhenDecryptFails(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('debug');

    $adapter = new TokenRevocationAdapter(
      accessTokenRepository: $this->createMock(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createMock(RefreshTokenRepositoryPort::class),
      tokenCache: $this->createMock(TokenCachePort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $logger,
      encryptionKey: $this->encryptionKey(),
    );

    self::assertFalse($adapter->revokeRefreshToken('invalid'));
  }

  #[Test]
  public function testRevokeAccessTokenRevokesToken(): void
  {
    $accessToken = $this->createAccessToken('access-id');
    $jwt = $this->createJwt(['jti' => 'access-id']);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('access-id')
      ->willReturn($accessToken);
    $accessTokenRepository->expects(self::once())
      ->method('save')
      ->with($accessToken);

    $tokenCache = $this->createMock(TokenCachePort::class);
    $tokenCache->expects(self::once())
      ->method('invalidate')
      ->with('access-id');

    $sessionTracking = $this->createMock(SessionTrackingPort::class);
    $sessionTracking->expects(self::once())
      ->method('revokeSessionByToken')
      ->with(null, 'access-id');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info');

    $adapter = new TokenRevocationAdapter(
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $this->createMock(RefreshTokenRepositoryPort::class),
      tokenCache: $tokenCache,
      sessionTracking: $sessionTracking,
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $logger,
      encryptionKey: $this->encryptionKey(),
    );

    self::assertTrue($adapter->revokeAccessToken($jwt));
  }

  #[Test]
  public function testRevokeAccessTokenReturnsFalseWhenMissingJti(): void
  {
    $jwt = $this->createJwt(['sub' => 'user-1']);

    $adapter = $this->createAdapter();

    self::assertFalse($adapter->revokeAccessToken($jwt));
  }

  #[Test]
  public function testRevokeAccessTokenReturnsFalseWhenJtiNotString(): void
  {
    $jwt = $this->createJwt(['jti' => 123]);

    $adapter = $this->createAdapter();

    self::assertFalse($adapter->revokeAccessToken($jwt));
  }

  #[Test]
  public function testRevokeAccessTokenReturnsFalseWhenTokenNotFound(): void
  {
    $jwt = $this->createJwt(['jti' => 'access-id']);

    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);
    $accessTokenRepository->expects(self::once())
      ->method('find')
      ->with('access-id')
      ->willReturn(null);

    $adapter = new TokenRevocationAdapter(
      accessTokenRepository: $accessTokenRepository,
      refreshTokenRepository: $this->createMock(RefreshTokenRepositoryPort::class),
      tokenCache: $this->createMock(TokenCachePort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $this->createMock(LoggerInterface::class),
      encryptionKey: $this->encryptionKey(),
    );

    self::assertFalse($adapter->revokeAccessToken($jwt));
  }

  #[Test]
  public function testRevokeAccessTokenReturnsFalseWhenTokenEncrypted(): void
  {
    $jwt = $this->createJwe(['jti' => 'access-id']);

    $adapter = $this->createAdapter();

    self::assertFalse($adapter->revokeAccessToken($jwt));
  }

  #[Test]
  public function testRevokeAllUserTokensLogs(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info');

    $adapter = new TokenRevocationAdapter(
      accessTokenRepository: $this->createMock(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createMock(RefreshTokenRepositoryPort::class),
      tokenCache: $this->createMock(TokenCachePort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $logger,
      encryptionKey: $this->encryptionKey(),
    );

    $adapter->revokeAllUserTokens('user-1');
  }

  private function createAdapter(): TokenRevocationAdapter
  {
    return new TokenRevocationAdapter(
      accessTokenRepository: $this->createMock(AccessTokenRepositoryPort::class),
      refreshTokenRepository: $this->createMock(RefreshTokenRepositoryPort::class),
      tokenCache: $this->createMock(TokenCachePort::class),
      sessionTracking: $this->createMock(SessionTrackingPort::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
      logger: $this->createMock(LoggerInterface::class),
      encryptionKey: $this->encryptionKey(),
    );
  }

  private function createAccessToken(string $identifier): AccessToken
  {
    return new AccessToken(
      identifier: $identifier,
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      expiry: new DateTimeImmutable('+1 hour'),
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: 'user-1',
    );
  }

  private function createRefreshToken(string $identifier, string $accessTokenId): RefreshToken
  {
    return new RefreshToken(
      identifier: $identifier,
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      accessTokenIdentifier: $accessTokenId,
      clientIdentifier: new OAuthClientIdentifier('client-123'),
    );
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function encryptPayload(array $payload): string
  {
    $json = json_encode($payload);
    self::assertIsString($json);

    return Crypto::encryptWithPassword($json, $this->encryptionKey());
  }

  /**
   * @param array<string, mixed> $claims
   */
  private function createJwt(array $claims): string
  {
    $header = ['alg' => 'none', 'typ' => 'JWT'];
    $payload = json_encode($claims);
    $headerJson = json_encode($header);

    self::assertIsString($payload);
    self::assertIsString($headerJson);

    $segments = [
      $this->base64UrlEncode($headerJson),
      $this->base64UrlEncode($payload),
      $this->base64UrlEncode('signature'),
    ];

    return implode('.', $segments);
  }

  private function base64UrlEncode(string $data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  private function encryptionKey(): string
  {
    return 'test-encryption-key';
  }

  /**
   * @param array<string, mixed> $claims
   */
  private function createJwe(array $claims): string
  {
    $header = ['alg' => 'RSA-OAEP', 'enc' => 'A256GCM', 'typ' => 'JWT'];
    $payload = json_encode($claims);
    $headerJson = json_encode($header);

    self::assertIsString($payload);
    self::assertIsString($headerJson);

    $segments = [
      $this->base64UrlEncode($headerJson),
      $this->base64UrlEncode('key'),
      $this->base64UrlEncode('iv'),
      $this->base64UrlEncode($payload),
      $this->base64UrlEncode('tag'),
    ];

    return implode('.', $segments);
  }
  // #endregion
}
