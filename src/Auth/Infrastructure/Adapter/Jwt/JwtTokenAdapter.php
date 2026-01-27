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

use function array_key_exists;
use function bin2hex;
use function filter_var;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function time;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

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

  /**
   * Property refreshTokenTtlShort.
   *
   * Short refresh token TTL in seconds.
   *
   * @since 1.0.0
   */
  private int $refreshTokenTtlShort;

  /**
   * Property includeEmailClaim.
   *
   * Whether to include the email claim in access tokens.
   *
   * @since 1.0.0
   */
  private bool $includeEmailClaim;

  /**
   * Property includeRbacClaims.
   *
   * Whether to include roles/permissions in access tokens.
   *
   * @since 1.0.0
   */
  private bool $includeRbacClaims;
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
   * @param non-empty-string $privateKeyPath
   * @param non-empty-string $publicKeyPath
   * @param non-empty-string $encryptionKey
   * @param non-empty-string $issuer
   * @param int $accessTokenTtl access token TTL in seconds
   * @param int $refreshTokenTtl refresh token long TTL in seconds
   * @param AuthorizationService|null $authorizationService authorization service for roles/permissions
   * @param int|null $refreshTokenTtlShort refresh token short TTL in seconds
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
    #[Autowire('%env(int:REFRESH_TOKEN_LIFETIME_SHORT)%')]
    ?int $refreshTokenTtlShort = null,
    #[Autowire('%env(bool:ACCESS_TOKEN_INCLUDE_EMAIL)%')]
    bool $includeEmailClaim = true,
    #[Autowire('%env(bool:ACCESS_TOKEN_INCLUDE_RBAC)%')]
    bool $includeRbacClaims = true,
  ) {
    $this->setEncryptionKey(key: $encryptionKey);
    $this->refreshTokenTtlShort = $refreshTokenTtlShort ?? $this->refreshTokenTtl;
    $this->includeEmailClaim = $includeEmailClaim;
    $this->includeRbacClaims = $includeRbacClaims;

    /** @var non-empty-string $privatePath */
    $privatePath = $this->privateKeyPath;
    /** @var non-empty-string $publicPath */
    $publicPath = $this->publicKeyPath;

    $this->jwtConfig = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file(path: $privatePath),
      verificationKey: InMemory::file(path: $publicPath),
    );
  }
  // #endregion

  // #region Methods
  public function generateTokens(string $userId, string $email, array $scopes = [], bool $rememberMe = true): array
  {
    $now = new DateTimeImmutable();
    $accessTokenExpiry = $now->add(new DateInterval("PT{$this->accessTokenTtl}S"));
    $refreshTtl = $rememberMe ? $this->refreshTokenTtl : $this->refreshTokenTtlShort;
    $refreshTokenExpiry = $now->add(new DateInterval("PT{$refreshTtl}S"));

    $accessTokenId = bin2hex(random_bytes(20));
    $refreshTokenId = bin2hex(random_bytes(20));

    $accessTokenBuilder = $this->jwtConfig->builder()
      ->issuedBy($this->issuer)
      ->permittedFor($this->issuer)
      ->identifiedBy($accessTokenId)
      ->relatedTo($userId)
      ->issuedAt($now)
      ->expiresAt($accessTokenExpiry)
      ->withClaim('scopes', $scopes);

    if ($this->includeEmailClaim && '' !== trim($email)) {
      $accessTokenBuilder = $accessTokenBuilder->withClaim('email', $email);
    }

    if ($this->includeRbacClaims && null !== $this->authorizationService) {
      $roles = $this->authorizationService->getUserRoleNames(userId: $userId);
      $permissions = $this->authorizationService->getUserPermissionNames(userId: $userId);
      $accessTokenBuilder = $accessTokenBuilder
        ->withClaim('roles', $roles)
        ->withClaim('permissions', $permissions);
    }

    $accessToken = $accessTokenBuilder->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

    /** @var non-empty-string $refreshTokenPayload */
    $refreshTokenPayload = json_encode([
      'refresh_token_id' => $refreshTokenId,
      'access_token_id' => $accessTokenId,
      'user_id' => $userId,
      'scopes' => $scopes,
      'expires_at' => $refreshTokenExpiry->getTimestamp(),
      'remember_me' => $rememberMe,
    ]);

    return [
      'access_token' => $accessToken->toString(),
      'refresh_token' => $this->encrypt($refreshTokenPayload),
      'token_type' => 'Bearer',
      'expires_in' => $this->accessTokenTtl,
    ];
  }

  public function generatePreAuthToken(
    string $userId,
    string $challengeToken,
    string $email = '',
    array $scopes = [],
    int $ttl = 300,
    bool $rememberMe = false,
  ): string {
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
      ->withClaim('email', $email)
      ->withClaim('scopes', $scopes)
      ->withClaim('challenge_token', $challengeToken)
      ->withClaim('remember_me', $rememberMe)
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
          $payload['expires_at'],
        )
        || !is_string($payload['refresh_token_id'])
        || !is_string($payload['access_token_id'])
        || !is_string($payload['user_id'])
        || !is_array($payload['scopes'])
        || !is_int($payload['expires_at'])
      ) {
        return null;
      }

      if (array_key_exists('remember_me', $payload) && !is_bool($payload['remember_me'])) {
        $parsed = filter_var($payload['remember_me'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (null !== $parsed) {
          $payload['remember_me'] = $parsed;
        } else {
          unset($payload['remember_me']);
        }
      }

      /** @var array{refresh_token_id: string, access_token_id: string, user_id: string, scopes: array<string>, expires_at: int, remember_me?: bool} $payload */
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
