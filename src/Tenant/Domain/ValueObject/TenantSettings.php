<?php

declare(strict_types=1);

namespace Tenant\Domain\ValueObject;

use function array_filter;
use function array_values;
use function is_array;
use function is_numeric;
use function is_scalar;

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
    // #region Constructor
    /**
     * Constructor.
     *
     * Initialize TenantSettings
     *
     * @since 1.0.0
     *
     * @param int          $accessTokenTtl     access token TTL in seconds
     * @param int          $refreshTokenTtl    refresh token TTL in seconds
     * @param bool         $requirePkce        whether PKCE is required
     * @param bool         $allowPublicClients whether public clients are allowed
     * @param list<string> $allowedScopes      the allowed OAuth2 scopes
     * @param string|null  $customIssuer       custom issuer URL for this tenant
     */
    public function __construct(
        public int $accessTokenTtl = 3600,
        public int $refreshTokenTtl = 86400,
        public bool $requirePkce = true,
        public bool $allowPublicClients = false,
        public array $allowedScopes = ['openid', 'profile', 'email'],
        public ?string $customIssuer = null,
    ) {
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
        $allowedScopes = is_array($allowedScopesRaw)
          ? array_values(array_filter($allowedScopesRaw, 'is_string'))
          : ['openid', 'profile', 'email'];

        return new self(
            accessTokenTtl: is_numeric($accessTokenTtl) ? (int) $accessTokenTtl : 3600,
            refreshTokenTtl: is_numeric($refreshTokenTtl) ? (int) $refreshTokenTtl : 86400,
            requirePkce: (bool) ($data['require_pkce'] ?? true),
            allowPublicClients: (bool) ($data['allow_public_clients'] ?? false),
            allowedScopes: $allowedScopes,
            customIssuer: is_scalar($customIssuer) ? (string) $customIssuer : null,
        );
    }
    // #endregion
}
