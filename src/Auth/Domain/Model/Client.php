<?php

declare(strict_types=1);

namespace Auth\Domain\Model;

use Shared\Domain\ValueObject\{
  GrantType,
  HashedSecret,
  OAuthClientIdentifier,
  Scope
};

use function in_array;

/**
 * Model Client
 * @final
 *
 * Represents a client application in the Auth context.
 * This is a projection/proxy of the Client entity from the Client module.
 *
 * @category Model
 * @package Auth\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Client
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of 
   * the Client class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OAuthClientIdentifier $identifier The client identifier.
   * @param string $name The client name.
   * @param HashedSecret $secret The hashed client secret.
   * @param list<string> $redirectUris The allowed redirect URIs.
   * @param list<GrantType> $grantTypes The allowed grant types.
   * @param list<Scope> $scopes The allowed scopes.
   * @param bool $isConfidential Whether the client is confidential (requires secret).
   */
  public function __construct(
    private readonly OAuthClientIdentifier $identifier,
    private readonly string $name,
    private readonly array $redirectUris,
    private readonly array $grantTypes,
    private readonly array $scopes,
    private readonly ?HashedSecret $secret = null,
    private readonly bool $isConfidential = true
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method identifier
   *
   * Gets the client identifier.
   * 
   * @access public
   * @since 1.0.0
   *
   * @return OAuthClientIdentifier The client identifier.
   */
  public function identifier(): OAuthClientIdentifier
  {
    return $this->identifier;
  }

  /**
   * Method name
   *
   * Gets the client name.
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
   * Method secret
   *
   * Gets the hashed client secret.
   * 
   * @access public
   * @since 1.0.0 
   *
   * @return HashedSecret|null The hashed client secret.
   */
  public function secret(): ?HashedSecret
  {
    return $this->secret;
  }

  /**
   * Method redirectUris
   *
   * Gets the allowed redirect URIs.
   * 
   * @access public
   * @since 1.0.0
   *
   * @return list<string> The allowed redirect URIs.
   */
  public function redirectUris(): array
  {
    return $this->redirectUris;
  }

  /**
   * Method grantTypes
   *
   * Gets the allowed grant types.
   * 
   * @access public
   * @since 1.0.0
   *
   * @return list<GrantType> The allowed grant types.
   */
  public function grantTypes(): array
  {
    return $this->grantTypes;
  }

  /**
   * Method scopes
   *
   * Gets the allowed scopes.
   * 
   * @access public
   * @since 1.0.0
   *
   * @return list<Scope> The allowed scopes.
   */
  public function scopes(): array
  {
    return $this->scopes;
  }

  /**
   * Method isConfidential
   *
   * Gets whether the client is confidential 
   * (requires secret).
   * 
   * @access public
   * @since 1.0.0
   *
   * @return bool True if the client is confidential, false otherwise.
   */
  public function isConfidential(): bool
  {
    return $this->isConfidential;
  }

  /**
   * Method validateRedirectUri
   *
   * Validates a redirect URI against the client's 
   * allowed redirect URIs.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $uri The redirect URI to validate.
   * 
   * @return bool True if the URI is valid, false otherwise.
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
   * Method supportsGrantType
   *
   * Validates a grant type against the client's 
   * allowed grant types.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param GrantType $grantType The grant type to validate.
   * 
   * @return bool True if the grant type is valid, false otherwise.
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
   * Method hasScope
   *
   * Validates a scope against the client's allowed scopes.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Scope $scope The scope to validate.
   * 
   * @return bool True if the scope is valid, false otherwise.
   */
  public function hasScope(Scope $scope): bool
  {
    return in_array(
      needle: $scope,
      haystack: $this->scopes,
      strict: true
    );
  }
  //#endregion
}
