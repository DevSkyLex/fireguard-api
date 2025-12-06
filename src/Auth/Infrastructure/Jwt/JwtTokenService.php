<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Jwt;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use DateInterval;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\CryptTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service JwtTokenService
 * @final
 *
 * Generates JWT tokens for direct user authentication.
 * This is separate from OAuth2 and used for the /api/auth/login endpoint.
 *
 * @category Service
 * @package Auth\Infrastructure\Jwt
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class JwtTokenService implements JwtTokenServicePort
{
  //#region Traits
  use CryptTrait;
  //#endregion

  //#region Properties
  /**
   * Property jwtConfig
   *
   * @var Configuration
   */
  private Configuration $jwtConfig;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @param string $privateKeyPath Path to the private key file.
   * @param string $publicKeyPath Path to the public key file.
   * @param string $encryptionKey Encryption key for refresh tokens.
   * @param string $issuer The JWT issuer (your domain).
   * @param int $accessTokenTtl Access token TTL in seconds.
   * @param int $refreshTokenTtl Refresh token TTL in seconds.
   */
  /**
   * @param non-empty-string $privateKeyPath
   * @param non-empty-string $publicKeyPath
   * @param non-empty-string $encryptionKey
   * @param non-empty-string $issuer
   */
  public function __construct(
    #[Autowire('%kernel.project_dir%/config/jwt/private.key')]
    private readonly string $privateKeyPath,
    #[Autowire('%kernel.project_dir%/config/jwt/public.key')]
    private readonly string $publicKeyPath,
    #[Autowire('%env(OAUTH_ENCRYPTION_KEY)%')]
    string $encryptionKey,
    #[Autowire('%env(OAUTH_ISSUER)%')]
    private readonly string $issuer,
    #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
    private readonly int $accessTokenTtl = 3600,
    #[Autowire('%env(int:REFRESH_TOKEN_LIFETIME_LONG)%')]
    private readonly int $refreshTokenTtl = 86400,
  ) {
    $this->setEncryptionKey(key: $encryptionKey);

    /** @var non-empty-string $privatePath */
    $privatePath = $this->privateKeyPath;
    /** @var non-empty-string $publicPath */
    $publicPath = $this->publicKeyPath;

    $this->jwtConfig = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file(path: $privatePath),
      verificationKey: InMemory::file(path: $publicPath)
    );
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function generateTokens(string $userId, string $email, array $scopes = []): array
  {
    $now = new DateTimeImmutable();
    $accessTokenExpiry = $now->add(new DateInterval("PT{$this->accessTokenTtl}S"));
    $refreshTokenExpiry = $now->add(new DateInterval("PT{$this->refreshTokenTtl}S"));

    $accessTokenId = bin2hex(random_bytes(20));
    $refreshTokenId = bin2hex(random_bytes(20));

    $accessToken = $this->jwtConfig->builder()
      ->issuedBy($this->issuer)
      ->permittedFor($this->issuer)
      ->identifiedBy($accessTokenId)
      ->relatedTo($userId)
      ->issuedAt($now)
      ->expiresAt($accessTokenExpiry)
      ->withClaim('email', $email)
      ->withClaim('scopes', $scopes)
      ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

    /** @var non-empty-string $refreshTokenPayload */
    $refreshTokenPayload = json_encode([
      'refresh_token_id' => $refreshTokenId,
      'access_token_id'  => $accessTokenId,
      'user_id'          => $userId,
      'scopes'           => $scopes,
      'expires_at'       => $refreshTokenExpiry->getTimestamp(),
    ]);

    return [
      'access_token'  => $accessToken->toString(),
      'refresh_token' => $this->encrypt($refreshTokenPayload),
      'token_type'    => 'Bearer',
      'expires_in'    => $this->accessTokenTtl,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function decodeRefreshToken(string $refreshToken): ?array
  {
    try {
      $decrypted = $this->decrypt($refreshToken);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload)) {
        return null;
      }

      if (isset($payload['expires_at']) && $payload['expires_at'] < time()) {
        return null;
      }

      if (
        !isset(
          $payload['refresh_token_id'],
          $payload['access_token_id'],
          $payload['user_id'],
          $payload['scopes'],
          $payload['expires_at']
        )
        || !is_string($payload['refresh_token_id'])
        || !is_string($payload['access_token_id'])
        || !is_string($payload['user_id'])
        || !is_array($payload['scopes'])
        || !is_int($payload['expires_at'])
      ) {
        return null;
      }

      /** @var array{refresh_token_id: string, access_token_id: string, user_id: string, scopes: array<string>, expires_at: int} $payload */
      return $payload;
    } catch (\Throwable) {
      return null;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getAccessTokenTtl(): int
  {
    return $this->accessTokenTtl;
  }

  /**
   * {@inheritDoc}
   */
  public function getRefreshTokenTtl(): int
  {
    return $this->refreshTokenTtl;
  }
  //#endregion
}
