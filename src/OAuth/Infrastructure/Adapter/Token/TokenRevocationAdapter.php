<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Token;

use Auth\Application\Port\Outbound\TokenRevocationPort as AuthTokenRevocationPort;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\{Parser, Plain};
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Port\Outbound\Token\{AccessTokenRepositoryPort, RefreshTokenRepositoryPort, TokenCachePort, TokenRevocationPort as OAuthTokenRevocationPort};
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
 * Adapter TokenRevocationAdapter.
 *
 * @category Adapter
 *
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
   * Constructor.
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository the access token repository
   * @param RefreshTokenRepositoryPort $refreshTokenRepository the refresh token repository
   * @param TokenCachePort $tokenCache the token cache
   * @param SessionTrackingPort $sessionTracking the session tracking service
   * @param LoggerInterface $logger the logger
   * @param string $encryptionKey the encryption key
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
  ) {
    $this->setEncryptionKey($encryptionKey);
  }
  // #endregion

  // #region Methods
  public function revokeRefreshToken(string $encryptedToken): bool
  {
    if ('' === $encryptedToken) {
      return false;
    }

    try {
      $decrypted = $this->decrypt($encryptedToken);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload) || !isset($payload['refresh_token_id'])) {
        return false;
      }

      $tokenId = $payload['refresh_token_id'];
      if (!is_string($tokenId)) {
        return false;
      }
      $accessTokenId = $payload['access_token_id'] ?? null;
      $this->revokeSessionByTokenIds(
        refreshTokenId: $tokenId,
        accessTokenId: is_string($accessTokenId) ? $accessTokenId : null,
      );
      $token = $this->refreshTokenRepository->find($tokenId);

      if (null !== $token) {
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

      return false;
    } catch (Throwable $e) {
      $this->logger->debug('Failed to revoke refresh token', [
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  public function revokeAccessToken(string $jwtToken): bool
  {
    if ('' === $jwtToken) {
      return false;
    }

    try {
      $parser = new Parser(new JoseEncoder());
      $parsedToken = $parser->parse($jwtToken);

      // @codeCoverageIgnoreStart
      // Unreachable: Plain is lcobucci/jwt's only concrete Token, and the parser
      // is constructed right above, so nothing else can be returned here.
      if (!$parsedToken instanceof Plain) {
        return false;
      }
      // @codeCoverageIgnoreEnd

      $claims = $parsedToken->claims();
      if (!$claims->has('jti')) {
        return false;
      }

      $tokenId = $claims->get('jti');
      if (!is_string($tokenId)) {
        return false;
      }
      $this->revokeSessionByTokenIds(refreshTokenId: null, accessTokenId: $tokenId);
      $token = $this->accessTokenRepository->find($tokenId);

      if (null !== $token) {
        $token->revoke();
        $this->accessTokenRepository->save($token);
        $this->tokenCache->invalidate($tokenId);

        $this->logger->info('Access token revoked', [
          'token_id' => $tokenId,
        ]);

        $userId = $claims->has('sub') ? $claims->get('sub') : null;
        $clientId = $claims->has('client_id') ? $claims->get('client_id') : null;

        $this->eventDispatcher->dispatch(new TokenRevokedEvent(
          tokenId: $tokenId,
          tokenType: 'access_token',
          reason: null,
          clientId: is_string($clientId) ? $clientId : null,
          userId: is_string($userId) ? $userId : null,
          ipAddress: null,
        ));

        return true;
      }

      return false;
    } catch (Throwable $e) {
      $this->logger->debug('Failed to revoke access token', [
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  public function revokeAllUserTokens(string $userId): void
  {
    // This would require additional repository methods
    // For now, this is a placeholder for future implementation
    $this->logger->info('Revoking all tokens for user', [
      'user_id' => $userId,
    ]);
  }

  /**
   * Method revokeSessionByTokenIds.
   *
   * Best-effort session revocation for a token.
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
