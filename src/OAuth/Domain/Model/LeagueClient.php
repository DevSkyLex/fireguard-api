<?php

declare(strict_types=1);

namespace OAuth\Domain\Model;

use OAuth\Domain\ValueObject\GrantType;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope;

/**
 * Model LeagueClient
 * @final
 *
 * Represents an OAuth 2.0 client for League OAuth2 Server integration.
 * This is a read model used by the League OAuth2 Server adapters.
 *
 * @category Model
 * @package OAuth\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LeagueClient
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * LeagueClient class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OAuthClientIdentifier $identifier The OAuth client identifier.
   * @param string $name The client name.
   * @param list<string> $redirectUris The redirect URIs.
   * @param list<GrantType> $grantTypes The grant types.
   * @param list<Scope> $scopes The scopes.
   * @param string|null $secret The hashed secret (null for public clients).
   * @param bool $isConfidential Whether the client is confidential.
   */
  public function __construct(
    private OAuthClientIdentifier $identifier,
    private string $name,
    private array $redirectUris = [],
    private array $grantTypes = [],
    private array $scopes = [],
    private ?string $secret = null,
    private bool $isConfidential = true
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method identifier
   *
   * Returns the OAuth client identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @return OAuthClientIdentifier The OAuth client identifier.
   */
  public function identifier(): OAuthClientIdentifier
  {
    return $this->identifier;
  }

  /**
   * Method name
   *
   * Returns the client name.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The client name.
   */
  public function name(): string
  {
    return $this->name;
  }

  /**
   * Method redirectUris
   *
   * Returns the redirect URIs.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The redirect URIs.
   */
  public function redirectUris(): array
  {
    return $this->redirectUris;
  }

  /**
   * Method grantTypes
   *
   * Returns the grant types.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<GrantType> The grant types.
   */
  public function grantTypes(): array
  {
    return $this->grantTypes;
  }

  /**
   * Method scopes
   *
   * Returns the scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<Scope> The scopes.
   */
  public function scopes(): array
  {
    return $this->scopes;
  }

  /**
   * Method secret
   *
   * Returns the hashed secret.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The hashed secret or null for public clients.
   */
  public function secret(): ?string
  {
    return $this->secret;
  }

  /**
   * Method isConfidential
   *
   * Returns whether the client is confidential.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if confidential, false otherwise.
   */
  public function isConfidential(): bool
  {
    return $this->isConfidential;
  }

  /**
   * Method validateRedirectUri
   *
   * Validates if a redirect URI is allowed for this client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $uri The redirect URI to validate.
   *
   * @return bool True if the URI is allowed, false otherwise.
   */
  public function validateRedirectUri(string $uri): bool
  {
    return in_array($uri, $this->redirectUris, true);
  }

  /**
   * Method supportsGrantType
   *
   * Checks if the client supports a specific grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GrantType $grantType The grant type to check.
   *
   * @return bool True if supported, false otherwise.
   */
  public function supportsGrantType(GrantType $grantType): bool
  {
    return in_array($grantType, $this->grantTypes, true);
  }

  /**
   * Method hasScope
   *
   * Checks if the client has a specific scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Scope $scope The scope to check.
   *
   * @return bool True if the client has the scope, false otherwise.
   */
  public function hasScope(Scope $scope): bool
  {
    return in_array($scope, $this->scopes, true);
  }
  //#endregion
}
