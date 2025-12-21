<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Jwt;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Authorization\Application\Service\AuthorizationService;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\CryptTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function bin2hex;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function time;

/**
 * Adapter JwtTokenAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class JwtTokenAdapter implements JwtTokenServicePort
{
  // #region Traits
  /**
   * Trait CryptTrait.
   *
   * Provides encryption and decryption
   * methods.
   *
   * @since 1.0.0
   * @see CryptTrait
   */
  use CryptTrait;
  // #endregion

  // #region Properties
  /**
   * Property jwtConfig.
   *
   * The JWT configuration.
   *
   * @since 1.0.0
   */
  private Configuration $jwtConfig;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * JwtTokenAdapter class.
   *
   * @since 1.0.0
   *
   * @param non-empty-string          $privateKeyPath
   * @param non-empty-string          $publicKeyPath
   * @param non-empty-string          $encryptionKey
   * @param non-empty-string          $issuer
   * @param int                       $accessTokenTtl       access token TTL in seconds
   * @param int                       $refreshTokenTtl      refresh token TTL in seconds
   * @param AuthorizationService|null $authorizationService authorization service for roles/permissions
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
    private readonly ?AuthorizationService $authorizationService = null,
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
  // #endregion

  // #region Methods
  public function generateTokens(string $userId, string $email, array $scopes = []): array
  {
    $now = new DateTimeImmutable();
    $accessTokenExpiry = $now->add(new DateInterval("PT{$this->accessTokenTtl}S"));
    $refreshTokenExpiry = $now->add(new DateInterval("PT{$this->refreshTokenTtl}S"));

    $accessTokenId = bin2hex(random_bytes(20));
    $refreshTokenId = bin2hex(random_bytes(20));

    // Get user roles and permissions from authorization service
    $roles = [];
    $permissions = [];
    if (null !== $this->authorizationService) {
      $roles = $this->authorizationService->getUserRoleNames(userId: $userId);
      $permissions = $this->authorizationService->getUserPermissionNames(userId: $userId);
    }

    $accessToken = $this->jwtConfig->builder()
      ->issuedBy($this->issuer)
      ->permittedFor($this->issuer)
      ->identifiedBy($accessTokenId)
      ->relatedTo($userId)
      ->issuedAt($now)
      ->expiresAt($accessTokenExpiry)
      ->withClaim('email', $email)
      ->withClaim('scopes', $scopes)
      ->withClaim('roles', $roles)
      ->withClaim('permissions', $permissions)
      ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

    /** @var non-empty-string $refreshTokenPayload */
    $refreshTokenPayload = json_encode([
      'refresh_token_id' => $refreshTokenId,
      'access_token_id' => $accessTokenId,
      'user_id' => $userId,
      'scopes' => $scopes,
      'expires_at' => $refreshTokenExpiry->getTimestamp(),
    ]);

    return [
      'access_token' => $accessToken->toString(),
      'refresh_token' => $this->encrypt($refreshTokenPayload),
      'token_type' => 'Bearer',
      'expires_in' => $this->accessTokenTtl,
    ];
  }

  public function generatePreAuthToken(string $userId, string $challengeToken, int $ttl = 300): string
  {
    if ('' === $userId) {
      throw new InvalidArgumentException('User ID cannot be empty');
    }

    $now = new DateTimeImmutable();
    $expiry = $now->add(new DateInterval("PT{$ttl}S"));
    $tokenId = bin2hex(random_bytes(20));

    $token = $this->jwtConfig->builder()
      ->issuedBy($this->issuer)
      ->permittedFor($this->issuer)
      ->identifiedBy($tokenId)
      ->relatedTo($userId)
      ->issuedAt($now)
      ->expiresAt($expiry)
      ->withClaim('scope', 'pre_auth')
      ->withClaim('challenge_token', $challengeToken)
      ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

    return $token->toString();
  }

  public function decodePreAuthToken(string $token): ?array
  {
    if ('' === $token) {
      return null;
    }

    try {
      $parser = new \Lcobucci\JWT\Token\Parser(new \Lcobucci\JWT\Encoding\JoseEncoder());

      /** @var \Lcobucci\JWT\Token\Plain $parsedToken */
      $parsedToken = $parser->parse($token);

      $validator = new \Lcobucci\JWT\Validation\Validator();

      // Validate signature
      if (!$validator->validate($parsedToken, new \Lcobucci\JWT\Validation\Constraint\SignedWith($this->jwtConfig->signer(), $this->jwtConfig->verificationKey()))) {
        return null;
      }

      // Validate expiry via constraints or manually
      if ($parsedToken->isExpired(new DateTimeImmutable())) {
        return null;
      }

      // Validate constraints (Issuer, etc) if needed, but signature + expiry is decent for now.

      return $parsedToken->claims()->all();
    } catch (Throwable) {
      return null;
    }
  }

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
    } catch (Throwable) {
      return null;
    }
  }

  public function getAccessTokenTtl(): int
  {
    return $this->accessTokenTtl;
  }

  public function getRefreshTokenTtl(): int
  {
    return $this->refreshTokenTtl;
  }
  // #endregion
}
