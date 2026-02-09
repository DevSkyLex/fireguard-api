<?php

declare(strict_types=1);

namespace Tenant\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function array_filter;
use function array_values;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function sprintf;
use function trim;

/**
 * ValueObject TenantSettings.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantSettings
{
  // #region Constants
  /**
   * Minimum access token TTL in seconds (5 minutes).
   */
  private const int MIN_ACCESS_TOKEN_TTL = 300;

  /**
   * Maximum access token TTL in seconds (24 hours).
   */
  private const int MAX_ACCESS_TOKEN_TTL = 86400;

  /**
   * Minimum refresh token TTL in seconds (1 hour).
   */
  private const int MIN_REFRESH_TOKEN_TTL = 3600;

  /**
   * Maximum refresh token TTL in seconds (30 days).
   */
  private const int MAX_REFRESH_TOKEN_TTL = 2592000;
  // #endregion

  // #region Properties
  public int $accessTokenTtl;

  public int $refreshTokenTtl;

  public bool $requirePkce;

  public bool $allowPublicClients;

  /**
   * @var list<string>
   */
  public array $allowedScopes;

  public ?string $customIssuer;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize TenantSettings
   *
   * @since 1.0.0
   *
   * @param int $accessTokenTtl access token TTL in seconds
   * @param int $refreshTokenTtl refresh token TTL in seconds
   * @param bool $requirePkce whether PKCE is required
   * @param bool $allowPublicClients whether public clients are allowed
   * @param list<string> $allowedScopes the allowed OAuth2 scopes
   * @param string|null $customIssuer custom issuer URL for this tenant
   */
  public function __construct(
    int $accessTokenTtl = 3600,
    int $refreshTokenTtl = 86400,
    bool $requirePkce = true,
    bool $allowPublicClients = false,
    array $allowedScopes = ['openid', 'profile', 'email'],
    ?string $customIssuer = null,
  ) {
    $this->accessTokenTtl = $accessTokenTtl;
    $this->refreshTokenTtl = $refreshTokenTtl;
    $this->requirePkce = $requirePkce;
    $this->allowPublicClients = $allowPublicClients;
    $this->allowedScopes = self::normalizeAllowedScopes($allowedScopes);
    $this->customIssuer = self::normalizeIssuer($customIssuer);

    $this->validateTtls();
    $this->validatePkceRequirements();
  }
  // #endregion

  // #region Methods
  /**
   * Method withAccessTokenTtl.
   *
   * Returns a new instance with modified access token TTL.
   *
   * @since 1.0.0
   *
   * @param int $ttl the new TTL
   *
   * @return self the new instance
   */
  public function withAccessTokenTtl(int $ttl): self
  {
    return new self(
      accessTokenTtl: $ttl,
      refreshTokenTtl: $this->refreshTokenTtl,
      requirePkce: $this->requirePkce,
      allowPublicClients: $this->allowPublicClients,
      allowedScopes: $this->allowedScopes,
      customIssuer: $this->customIssuer,
    );
  }

  /**
   * Method withRefreshTokenTtl.
   *
   * Returns a new instance with modified refresh token TTL.
   *
   * @since 1.0.0
   *
   * @param int $ttl the new TTL
   *
   * @return self the new instance
   */
  public function withRefreshTokenTtl(int $ttl): self
  {
    return new self(
      accessTokenTtl: $this->accessTokenTtl,
      refreshTokenTtl: $ttl,
      requirePkce: $this->requirePkce,
      allowPublicClients: $this->allowPublicClients,
      allowedScopes: $this->allowedScopes,
      customIssuer: $this->customIssuer,
    );
  }

  /**
   * Method toArray.
   *
   * Returns the settings as an array.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the settings array
   */
  public function toArray(): array
  {
    return [
      'access_token_ttl' => $this->accessTokenTtl,
      'refresh_token_ttl' => $this->refreshTokenTtl,
      'require_pkce' => $this->requirePkce,
      'allow_public_clients' => $this->allowPublicClients,
      'allowed_scopes' => $this->allowedScopes,
      'custom_issuer' => $this->customIssuer,
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates settings from an array.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    $accessTokenTtl = $data['access_token_ttl'] ?? 3600;
    $refreshTokenTtl = $data['refresh_token_ttl'] ?? 86400;
    $customIssuer = $data['custom_issuer'] ?? null;
    $allowedScopesRaw = $data['allowed_scopes'] ?? ['openid', 'profile', 'email'];
    /** @var list<string> $allowedScopes */
    $allowedScopes = self::normalizeAllowedScopes(
      is_array($allowedScopesRaw) ? $allowedScopesRaw : ['openid', 'profile', 'email'],
    );

    return new self(
      accessTokenTtl: is_numeric($accessTokenTtl) ? (int) $accessTokenTtl : 3600,
      refreshTokenTtl: is_numeric($refreshTokenTtl) ? (int) $refreshTokenTtl : 86400,
      requirePkce: (bool) ($data['require_pkce'] ?? true),
      allowPublicClients: (bool) ($data['allow_public_clients'] ?? false),
      allowedScopes: $allowedScopes,
      customIssuer: is_scalar($customIssuer) ? (string) $customIssuer : null,
    );
  }

  /**
   * Ensures TTL values are within supported bounds.
   */
  private function validateTtls(): void
  {
    if ($this->accessTokenTtl < self::MIN_ACCESS_TOKEN_TTL || $this->accessTokenTtl > self::MAX_ACCESS_TOKEN_TTL) {
      throw InvalidValueException::because(sprintf(
        'Access token TTL must be between %d and %d seconds.',
        self::MIN_ACCESS_TOKEN_TTL,
        self::MAX_ACCESS_TOKEN_TTL,
      ));
    }

    if ($this->refreshTokenTtl < self::MIN_REFRESH_TOKEN_TTL || $this->refreshTokenTtl > self::MAX_REFRESH_TOKEN_TTL) {
      throw InvalidValueException::because(sprintf(
        'Refresh token TTL must be between %d and %d seconds.',
        self::MIN_REFRESH_TOKEN_TTL,
        self::MAX_REFRESH_TOKEN_TTL,
      ));
    }
  }

  /**
   * Enforces PKCE requirements for public clients.
   */
  private function validatePkceRequirements(): void
  {
    if ($this->allowPublicClients && !$this->requirePkce) {
      throw InvalidValueException::because('Public clients require PKCE to be enabled.');
    }
  }

  /**
   * @param array<mixed> $scopes
   *
   * @return list<string>
   */
  private static function normalizeAllowedScopes(array $scopes): array
  {
    $normalized = [];

    foreach ($scopes as $scope) {
      if (!is_string($scope)) {
        continue;
      }
      $trimmed = trim($scope);
      if ('' === $trimmed) {
        continue;
      }
      $normalized[] = $trimmed;
    }

    if ([] === $normalized) {
      return ['openid', 'profile', 'email'];
    }

    return array_values(array_filter($normalized, 'is_string'));
  }

  /**
   * @return string|null normalized issuer or null when empty
   */
  private static function normalizeIssuer(?string $issuer): ?string
  {
    if (null === $issuer) {
      return null;
    }

    $trimmed = trim($issuer);

    return '' === $trimmed ? null : $trimmed;
  }
  // #endregion
}
