<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Token;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use League\OAuth2\Server\CryptTrait;
use OAuth\Application\Port\Outbound\Token\AccessTokenRepositoryPort;
use OAuth\Application\Port\Outbound\Token\RefreshTokenRepositoryPort;
use OAuth\Application\Port\Outbound\Token\TokenCachePort;
use OAuth\Application\Port\Outbound\Token\TokenRevocationPort;
use Psr\Log\LoggerInterface;
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
final class TokenRevocationAdapter implements TokenRevocationPort
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
   * @param LoggerInterface $logger the logger
   * @param string $encryptionKey the encryption key
   */
  public function __construct(
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
    private readonly RefreshTokenRepositoryPort $refreshTokenRepository,
    private readonly TokenCachePort $tokenCache,
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
      $token = $this->refreshTokenRepository->find($tokenId);

      if (null !== $token) {
        $token->revoke();
        $this->refreshTokenRepository->save($token);
        $this->tokenCache->invalidate($tokenId);

        $this->logger->info('Refresh token revoked', [
          'token_id' => $tokenId,
        ]);

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

      if (!$parsedToken instanceof Plain) {
        return false;
      }

      $claims = $parsedToken->claims();
      if (!$claims->has('jti')) {
        return false;
      }

      $tokenId = $claims->get('jti');
      if (!is_string($tokenId)) {
        return false;
      }
      $token = $this->accessTokenRepository->find($tokenId);

      if (null !== $token) {
        $token->revoke();
        $this->accessTokenRepository->save($token);
        $this->tokenCache->invalidate($tokenId);

        $this->logger->info('Access token revoked', [
          'token_id' => $tokenId,
        ]);

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
  // #endregion
}
