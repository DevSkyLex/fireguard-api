<?php

declare(strict_types=1);

namespace Auth\Domain\Model;

use OAuth\Domain\ValueObject\{
    GrantType,
    OAuthClientIdentifier,
    Scope
};
use Shared\Domain\ValueObject\HashedSecret;

use function in_array;

/**
 * Model Client.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Client
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of
     * the Client class.
     *
     * @since 1.0.0
     *
     * @param OAuthClientIdentifier $identifier     the client identifier
     * @param string                $name           the client name
     * @param HashedSecret          $secret         the hashed client secret
     * @param list<string>          $redirectUris   the allowed redirect URIs
     * @param list<GrantType>       $grantTypes     the allowed grant types
     * @param list<Scope>           $scopes         the allowed scopes
     * @param bool                  $isConfidential whether the client is confidential (requires secret)
     */
    public function __construct(
        private readonly OAuthClientIdentifier $identifier,
        private readonly string $name,
        private readonly array $redirectUris,
        private readonly array $grantTypes,
        private readonly array $scopes,
        private readonly ?HashedSecret $secret = null,
        private readonly bool $isConfidential = true,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method identifier.
     *
     * Gets the client identifier.
     *
     * @since 1.0.0
     *
     * @return OAuthClientIdentifier the client identifier
     */
    public function identifier(): OAuthClientIdentifier
    {
        return $this->identifier;
    }

    /**
     * Method name.
     *
     * Gets the client name.
     *
     * @since 1.0.0
     *
     * @return string the client name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Method secret.
     *
     * Gets the hashed client secret.
     *
     * @since 1.0.0
     *
     * @return HashedSecret|null the hashed client secret
     */
    public function secret(): ?HashedSecret
    {
        return $this->secret;
    }

    /**
     * Method redirectUris.
     *
     * Gets the allowed redirect URIs.
     *
     * @since 1.0.0
     *
     * @return list<string> the allowed redirect URIs
     */
    public function redirectUris(): array
    {
        return $this->redirectUris;
    }

    /**
     * Method grantTypes.
     *
     * Gets the allowed grant types.
     *
     * @since 1.0.0
     *
     * @return list<GrantType> the allowed grant types
     */
    public function grantTypes(): array
    {
        return $this->grantTypes;
    }

    /**
     * Method scopes.
     *
     * Gets the allowed scopes.
     *
     * @since 1.0.0
     *
     * @return list<Scope> the allowed scopes
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Method isConfidential.
     *
     * Gets whether the client is confidential
     * (requires secret).
     *
     * @since 1.0.0
     *
     * @return bool true if the client is confidential, false otherwise
     */
    public function isConfidential(): bool
    {
        return $this->isConfidential;
    }

    /**
     * Method validateRedirectUri.
     *
     * Validates a redirect URI against the client's
     * allowed redirect URIs.
     *
     * @since 1.0.0
     *
     * @param string $uri the redirect URI to validate
     *
     * @return bool true if the URI is valid, false otherwise
     */
    public function validateRedirectUri(string $uri): bool
    {
        return in_array(
            needle: $uri,
            haystack: $this->redirectUris,
            strict: true
        );
    }

    /**
     * Method supportsGrantType.
     *
     * Validates a grant type against the client's
     * allowed grant types.
     *
     * @since 1.0.0
     *
     * @param GrantType $grantType the grant type to validate
     *
     * @return bool true if the grant type is valid, false otherwise
     */
    public function supportsGrantType(GrantType $grantType): bool
    {
        return in_array(
            needle: $grantType,
            haystack: $this->grantTypes,
            strict: true
        );
    }

    /**
     * Method hasScope.
     *
     * Validates a scope against the client's allowed scopes.
     *
     * @since 1.0.0
     *
     * @param Scope $scope the scope to validate
     *
     * @return bool true if the scope is valid, false otherwise
     */
    public function hasScope(Scope $scope): bool
    {
        return in_array(
            needle: $scope,
            haystack: $this->scopes,
            strict: true
        );
    }
    // #endregion
}
