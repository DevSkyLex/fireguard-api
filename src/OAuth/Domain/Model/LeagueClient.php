<?php

declare(strict_types=1);

namespace OAuth\Domain\Model;

use OAuth\Domain\ValueObject\GrantType;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope;

use function in_array;

/**
 * Model LeagueClient.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LeagueClient
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * LeagueClient class.
     *
     * @since 1.0.0
     *
     * @param OAuthClientIdentifier $identifier     the OAuth client identifier
     * @param string                $name           the client name
     * @param list<string>          $redirectUris   the redirect URIs
     * @param list<GrantType>       $grantTypes     the grant types
     * @param list<Scope>           $scopes         the scopes
     * @param string|null           $secret         the hashed secret (null for public clients)
     * @param bool                  $isConfidential whether the client is confidential
     */
    public function __construct(
        private OAuthClientIdentifier $identifier,
        private string $name,
        private array $redirectUris = [],
        private array $grantTypes = [],
        private array $scopes = [],
        private ?string $secret = null,
        private bool $isConfidential = true,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method identifier.
     *
     * Returns the OAuth client identifier.
     *
     * @since 1.0.0
     *
     * @return OAuthClientIdentifier the OAuth client identifier
     */
    public function identifier(): OAuthClientIdentifier
    {
        return $this->identifier;
    }

    /**
     * Method name.
     *
     * Returns the client name.
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
     * Method redirectUris.
     *
     * Returns the redirect URIs.
     *
     * @since 1.0.0
     *
     * @return list<string> the redirect URIs
     */
    public function redirectUris(): array
    {
        return $this->redirectUris;
    }

    /**
     * Method grantTypes.
     *
     * Returns the grant types.
     *
     * @since 1.0.0
     *
     * @return list<GrantType> the grant types
     */
    public function grantTypes(): array
    {
        return $this->grantTypes;
    }

    /**
     * Method scopes.
     *
     * Returns the scopes.
     *
     * @since 1.0.0
     *
     * @return list<Scope> the scopes
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Method secret.
     *
     * Returns the hashed secret.
     *
     * @since 1.0.0
     *
     * @return string|null the hashed secret or null for public clients
     */
    public function secret(): ?string
    {
        return $this->secret;
    }

    /**
     * Method isConfidential.
     *
     * Returns whether the client is confidential.
     *
     * @since 1.0.0
     *
     * @return bool true if confidential, false otherwise
     */
    public function isConfidential(): bool
    {
        return $this->isConfidential;
    }

    /**
     * Method validateRedirectUri.
     *
     * Validates if a redirect URI is allowed for this client.
     *
     * @since 1.0.0
     *
     * @param string $uri the redirect URI to validate
     *
     * @return bool true if the URI is allowed, false otherwise
     */
    public function validateRedirectUri(string $uri): bool
    {
        return in_array($uri, $this->redirectUris, true);
    }

    /**
     * Method supportsGrantType.
     *
     * Checks if the client supports a specific grant type.
     *
     * @since 1.0.0
     *
     * @param GrantType $grantType the grant type to check
     *
     * @return bool true if supported, false otherwise
     */
    public function supportsGrantType(GrantType $grantType): bool
    {
        return in_array($grantType, $this->grantTypes, true);
    }

    /**
     * Method hasScope.
     *
     * Checks if the client has a specific scope.
     *
     * @since 1.0.0
     *
     * @param Scope $scope the scope to check
     *
     * @return bool true if the client has the scope, false otherwise
     */
    public function hasScope(Scope $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
    // #endregion
}
