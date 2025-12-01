<?php

declare(strict_types=1);

namespace Auth\Application\Service;

use DateInterval;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use League\OAuth2\Server\CryptTrait;

/**
 * Service JwtTokenService
 * @final
 *
 * Generates JWT tokens for direct user authentication.
 * This is separate from OAuth2 and used for the /api/auth/login endpoint.
 *
 * @category Service
 * @package Auth\Application\Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class JwtTokenService implements JwtTokenServicePort
{
  //#region Traits
  /**
   * Trait CryptTrait
   *
   * Provides encryption and decryption methods.
   *
   * @since 1.0.0
   */
  use CryptTrait;
  //#endregion

  //#region Properties
  /**
   * Property jwtConfig
   *
   * JWT configuration.
   *
   * @access private
   *
   * @var Configuration
   */
  private Configuration $jwtConfig;
  //#endregion

  //#region Methods
  /**
   * Constructor
   *
   * Initialize the service
   *
   * @access public
   * @since 1.0.0
   *
   * @param non-empty-string $privateKeyPath Path to the private key file.
   * @param non-empty-string $publicKeyPath Path to the public key file.
   * @param string $encryptionKey Encryption key for refresh tokens.
   * @param non-empty-string $issuer The JWT issuer (your domain).
   * @param int $accessTokenTtl Access token TTL in seconds.
   * @param int $refreshTokenTtl Refresh token TTL in seconds.
   */
  public function __construct(
    private readonly string $privateKeyPath,
    private readonly string $publicKeyPath,
    string $encryptionKey,
    private readonly string $issuer,
    private readonly int $accessTokenTtl = 3600,
    private readonly int $refreshTokenTtl = 86400,
  ) {
    $this->setEncryptionKey(key: $encryptionKey);

    $this->jwtConfig = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file(path: $this->privateKeyPath),
      verificationKey: InMemory::file(path: $this->publicKeyPath)
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method generateTokens
   *
   * Generate tokens for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param non-empty-string $userId The user ID.
   * @param string $email The user email.
   * @param array<string> $scopes The granted scopes.
   *
   * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
   */
  public function generateTokens(string $userId, string $email, array $scopes = []): array
  {
    $now = new DateTimeImmutable();
    $accessTokenExpiry = $now->add(interval: new DateInterval(
      duration: "PT{$this->accessTokenTtl}S"
    ));

    $refreshTokenExpiry = $now->add(interval: new DateInterval(
      duration: "PT{$this->refreshTokenTtl}S"
    ));

    // Generate unique token IDs
    $accessTokenId = bin2hex(random_bytes(20));
    $refreshTokenId = bin2hex(random_bytes(20));

    // Build access token (JWT)
    $accessToken = $this->jwtConfig->builder()
      ->issuedBy(issuer: $this->issuer)
      ->permittedFor(permittedFor: $this->issuer)
      ->identifiedBy(id: $accessTokenId)
      ->relatedTo(subject: $userId)
      ->issuedAt(issuedAt: $now)
      ->expiresAt(expiration: $accessTokenExpiry)
      ->withClaim(name: 'email', value: $email)
      ->withClaim(name: 'scopes', value: $scopes)
      ->getToken(signer: $this->jwtConfig->signer(), key: $this->jwtConfig->signingKey());

    // Build refresh token (encrypted payload)
    /** @var non-empty-string $refreshTokenPayload */
    $refreshTokenPayload = json_encode([
      'refresh_token_id' => $refreshTokenId,
      'access_token_id'  => $accessTokenId,
      'user_id'          => $userId,
      'scopes'           => $scopes,
      'expires_at'       => $refreshTokenExpiry->getTimestamp(),
    ]);

    $refreshToken = $this->encrypt(unencryptedData: $refreshTokenPayload);

    return [
      'access_token'  => $accessToken->toString(),
      'refresh_token' => $refreshToken,
      'token_type'    => 'Bearer',
      'expires_in'    => $this->accessTokenTtl,
    ];
  }

  /**
   * Method decodeRefreshToken
   *
   * Decode a refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $refreshToken The encrypted refresh token.
   *
   * @return array{refresh_token_id: string, access_token_id: string, user_id: string, scopes: array<string>, expires_at: int}|null
   */
  public function decodeRefreshToken(string $refreshToken): ?array
  {
    try {
      $decrypted = $this->decrypt($refreshToken);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload)) {
        return null;
      }

      // Check expiration
      if (isset($payload['expires_at']) && $payload['expires_at'] < time()) {
        return null;
      }

      // Validate required keys
      if (
        !isset($payload['refresh_token_id'], $payload['access_token_id'], $payload['user_id'], $payload['scopes'], $payload['expires_at'])
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
   * Method getAccessTokenTtl
   *
   * Get access token TTL in seconds.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The access token TTL in seconds.
   */
  public function getAccessTokenTtl(): int
  {
    return $this->accessTokenTtl;
  }

  /**
   * Method getRefreshTokenTtl
   *
   * Get refresh token TTL in seconds.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int The refresh token TTL in seconds.
   */
  public function getRefreshTokenTtl(): int
  {
    return $this->refreshTokenTtl;
  }

  //#endregion
}
