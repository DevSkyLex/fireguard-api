<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Token;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use Auth\Application\Port\Outbound\TokenRevocationPort;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use League\OAuth2\Server\CryptTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Service TokenRevocationService
 * @final
 *
 * Handles token revocation for both access and refresh tokens.
 *
 * @category Service
 * @package Auth\Infrastructure\Token
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenRevocationService implements TokenRevocationPort
{
  //#region Traits
  use CryptTrait;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository The access token repository.
   * @param RefreshTokenRepositoryPort $refreshTokenRepository The refresh token repository.
   * @param LoggerInterface $logger The logger.
   * @param string $encryptionKey The encryption key.
   */
  public function __construct(
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
    private readonly RefreshTokenRepositoryPort $refreshTokenRepository,
    #[Autowire(service: 'monolog.logger.security')]
    private readonly LoggerInterface $logger,
    #[Autowire('%env(OAUTH_ENCRYPTION_KEY)%')]
    string $encryptionKey,
  ) {
    $this->setEncryptionKey($encryptionKey);
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function revokeRefreshToken(string $encryptedToken): bool
  {
    if ($encryptedToken === '') {
      return false;
    }

    try {
      $decrypted = $this->decrypt($encryptedToken);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload) || !isset($payload['refresh_token_id'])) {
        return false;
      }

      $tokenId = $payload['refresh_token_id'];
      $token = $this->refreshTokenRepository->find($tokenId);

      if ($token !== null) {
        $token->revoke();
        $this->refreshTokenRepository->save($token);

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

  /**
   * {@inheritDoc}
   */
  public function revokeAccessToken(string $jwtToken): bool
  {
    if ($jwtToken === '') {
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
      $token = $this->accessTokenRepository->find($tokenId);

      if ($token !== null) {
        $token->revoke();
        $this->accessTokenRepository->save($token);

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

  /**
   * {@inheritDoc}
   */
  public function revokeAllUserTokens(string $userId): void
  {
    // This would require additional repository methods
    // For now, this is a placeholder for future implementation
    $this->logger->info('Revoking all tokens for user', [
      'user_id' => $userId,
    ]);
  }
  //#endregion
}
