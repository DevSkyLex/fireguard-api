<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Token;

use Auth\Application\Port\Outbound\TokenRevocationPort as AuthTokenRevocationPort;
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Port\Outbound\Token\{AccessTokenRepositoryPort, JwtParserPort, RefreshTokenRepositoryPort, TokenCachePort, TokenRevocationPort as OAuthTokenRevocationPort, UserTokenRevocationPort};
use OAuth\Domain\Event\Token\TokenRevokedEvent;
use Psr\Log\LoggerInterface;
use Session\Application\Port\Inbound\Tracking\SessionTrackingPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Class TokenRevocationAdapter
 *
 * Revokes individual stored OAuth or interactive-session tokens, invalidates token-cache entries and publishes
 * revocation events. Bulk revocation commits every user-bound OAuth grant family before invalidating caches.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenRevocationAdapter implements OAuthTokenRevocationPort, AuthTokenRevocationPort
{
  // #region Traits
  use CryptTrait;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Configures token storage, session revocation, event publication and encryption used to process revocation requests.
   *
   * @access public
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository reads and persists access-token state
   * @param RefreshTokenRepositoryPort $refreshTokenRepository reads and persists refresh-token state
   * @param TokenCachePort $tokenCache invalidates cached token lookups
   * @param SessionTrackingPort $sessionTracking revokes the session anchored to a token pair
   * @param EventDispatcherPort $eventDispatcher publishes token-revoked events
   * @param LoggerInterface $logger records revocation outcomes on the security channel
   * @param string $encryptionKey key used to decrypt refresh-token payloads
   * @param UserTokenRevocationPort $userTokenRevocation atomically revokes every stored user grant
   * @param JwtParserPort $jwtParser validates signed access tokens before revocation
   *
   * @return void
   */
  public function __construct(
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
    private readonly RefreshTokenRepositoryPort $refreshTokenRepository,
    private readonly TokenCachePort $tokenCache,
    private readonly SessionTrackingPort $sessionTracking,
    private readonly EventDispatcherPort $eventDispatcher,
    #[Autowire(service: 'monolog.logger.security')]
    private readonly LoggerInterface $logger,
    #[Autowire('%env(OAUTH_ENCRYPTION_KEY)%')]
    string $encryptionKey,
    private readonly UserTokenRevocationPort $userTokenRevocation,
    private readonly JwtParserPort $jwtParser,
  ) {
    $this->setEncryptionKey($encryptionKey);
  }
  // #endregion

  // #region Methods
  /**
   * Method revokeRefreshToken
   *
   * Decrypts the refresh-token payload, revokes its stored token and associated session, and emits a revocation event.
   *
   * @access public
   *
   * @param string $encryptedToken the encrypted refresh-token value from the cookie
   *
   * @return bool whether a stored refresh token was revoked
   */
  public function revokeRefreshToken(string $encryptedToken): bool
  {
    if ('' === $encryptedToken) {
      return false;
    }

    try {
      $decrypted = $this->decrypt($encryptedToken);
      $payload = json_decode($decrypted, true);

      return $this->revokeRefreshTokenPayload($payload);
    } catch (Throwable $e) {
      $this->logger->debug('Failed to revoke refresh token', [
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  /**
   * Method revokeAccessToken
   *
   * Reads the JWT identifier, revokes its stored access token and session, and emits a revocation event.
   *
   * @access public
   *
   * @param string $jwtToken the serialized access-token JWT
   *
   * @return bool whether a stored access token was revoked
   */
  public function revokeAccessToken(string $jwtToken): bool
  {
    if ('' === $jwtToken) {
      return false;
    }

    try {
      $tokenId = $this->validatedAccessTokenId($jwtToken);

      return null !== $tokenId && $this->revokeStoredAccessToken($tokenId);
    } catch (Throwable $e) {
      $this->logger->debug('Failed to revoke access token', [
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  /**
   * Method revokeAllUserTokens
   *
   * Atomically revokes all user-bound OAuth grants and invalidates their cached introspection data.
   *
   * @access public
   *
   * @param string $userId the user whose tokens were requested for revocation
   *
   * @return void
   */
  public function revokeAllUserTokens(string $userId): void
  {
    foreach ($this->userTokenRevocation->revokeForUser($userId) as $tokenId) {
      $this->tokenCache->invalidate($tokenId);
    }

    $this->logger->info('All OAuth grants revoked for user', [
      'user_id' => $userId,
    ]);
  }

  /**
   * Method revokeRefreshTokenPayload
   *
   * Rejects unusable decoded refresh identifiers before session or storage access and publishes only after the stored revocation.
   *
   * @access private
   *
   * @param mixed $payload the decrypted JSON value
   *
   * @return bool whether a stored refresh token was revoked
   */
  private function revokeRefreshTokenPayload(mixed $payload): bool
  {
    if (!is_array($payload) || !isset($payload['refresh_token_id']) || !is_string($payload['refresh_token_id'])) {
      return false;
    }

    $tokenId = $payload['refresh_token_id'];
    $accessTokenId = $payload['access_token_id'] ?? null;
    $this->revokeSessionByTokenIds(
      refreshTokenId: $tokenId,
      accessTokenId: is_string($accessTokenId) ? $accessTokenId : null,
    );
    $token = $this->refreshTokenRepository->find($tokenId);
    if (null === $token) {
      return false;
    }

    $token->revoke();
    $this->refreshTokenRepository->save($token);
    $this->tokenCache->invalidate($tokenId);

    $this->logger->info('Refresh token revoked', [
      'token_id' => $tokenId,
    ]);

    $userId = null;
    if (isset($payload['user_id']) && is_string($payload['user_id'])) {
      $userId = $payload['user_id'];
    }

    $this->eventDispatcher->dispatch(new TokenRevokedEvent(
      tokenId: $tokenId,
      tokenType: 'refresh_token',
      reason: null,
      clientId: null,
      userId: $userId,
      ipAddress: null,
    ));

    return true;
  }

  /**
   * Method validatedAccessTokenId
   *
   * Verifies the JWT before reading its identifier so untrusted claims cannot drive session or token revocation.
   *
   * @access private
   *
   * @param string $jwtToken the serialized access-token JWT
   *
   * @return string|null a usable identifier from a validated token
   */
  private function validatedAccessTokenId(string $jwtToken): ?string
  {
    if (!$this->jwtParser->validate($jwtToken)) {
      return null;
    }

    $claims = $this->jwtParser->parse($jwtToken);
    $tokenId = $claims['jti'] ?? null;

    return is_string($tokenId) && '' !== $tokenId ? $tokenId : null;
  }

  /**
   * Method revokeStoredAccessToken
   *
   * Revokes the verified token's session and stored access token before cache invalidation and event publication.
   *
   * @access private
   *
   * @param string $tokenId the identifier obtained after JWT validation
   *
   * @return bool whether a stored access token was revoked
   */
  private function revokeStoredAccessToken(string $tokenId): bool
  {
    $this->revokeSessionByTokenIds(refreshTokenId: null, accessTokenId: $tokenId);
    $token = $this->accessTokenRepository->find($tokenId);
    if (null === $token) {
      return false;
    }

    $token->revoke();
    $this->accessTokenRepository->save($token);
    $this->tokenCache->invalidate($tokenId);

    $this->logger->info('Access token revoked', [
      'token_id' => $tokenId,
    ]);

    $userId = $token->userIdentifier();
    $clientId = (string) $token->clientIdentifier();

    $this->eventDispatcher->dispatch(new TokenRevokedEvent(
      tokenId: $tokenId,
      tokenType: 'access_token',
      reason: null,
      clientId: $clientId,
      userId: is_string($userId) ? $userId : null,
      ipAddress: null,
    ));

    return true;
  }

  /**
   * Method revokeSessionByTokenIds
   *
   * Best-effort session revocation for a token.
   *
   * @access private
   *
   * @param string|null $refreshTokenId the refresh token ID
   * @param string|null $accessTokenId the access token ID
   *
   * @return void no return value
   */
  private function revokeSessionByTokenIds(?string $refreshTokenId, ?string $accessTokenId): void
  {
    try {
      $this->sessionTracking->revokeSessionByToken(
        refreshTokenId: $refreshTokenId,
        accessTokenId: $accessTokenId,
      );
    } catch (Throwable) {
      // Best-effort session revocation.
    }
  }
  // #endregion
}
