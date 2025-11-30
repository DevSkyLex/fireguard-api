<?php

declare(strict_types=1);

namespace Auth\Domain\Model;

use DateTimeImmutable;
use Shared\Domain\ValueObject\OAuthClientIdentifier;
use Shared\Domain\ValueObject\Scopes;

/**
 * Class AuthCode
 * @final
 *
 * Domain model for OAuth2 Authorization Code.
 *
 * @category Model
 * @package Auth\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthCode
{
  /**
   * Constructor
   * 
   * Initializes a new instance of the AuthCode class.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param string $identifier The auth code identifier.
   * @param DateTimeImmutable $expiryDateTime The expiry date time.
   * @param OAuthClientIdentifier $clientIdentifier The client identifier.
   * @param string|null $userIdentifier The user identifier.
   * @param Scopes $scopes The scopes.
   * @param string|null $redirectUri The redirect URI.
   * @param bool $isRevoked Whether the code is revoked.
   */
  public function __construct(
    private readonly string $identifier,
    private readonly DateTimeImmutable $expiryDateTime,
    private readonly OAuthClientIdentifier $clientIdentifier,
    private readonly ?string $userIdentifier,
    private readonly Scopes $scopes,
    private readonly ?string $redirectUri,
    private bool $isRevoked = false
  ) {
  }

  /**
   * Method identifier
   * 
   * Gets the auth code identifier.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return string The identifier.
   */
  public function identifier(): string
  {
    return $this->identifier;
  }

  /**
   * Method expiryDateTime
   * 
   * Gets the expiry date time.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return DateTimeImmutable The expiry date time.
   */
  public function expiryDateTime(): DateTimeImmutable
  {
    return $this->expiryDateTime;
  }

  /**
   * Method clientIdentifier
   * 
   * Gets the client identifier.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return OAuthClientIdentifier The client identifier.
   */
  public function clientIdentifier(): OAuthClientIdentifier
  {
    return $this->clientIdentifier;
  }

  /**
   * Method userIdentifier
   * 
   * Gets the user identifier.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return string|null The user identifier.
   */
  public function userIdentifier(): ?string
  {
    return $this->userIdentifier;
  }

  /**
   * Method scopes
   * 
   * Gets the scopes.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return Scopes The scopes.
   */
  public function scopes(): Scopes
  {
    return $this->scopes;
  }

  /**
   * Method redirectUri
   * 
   * Gets the redirect URI.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return string|null The redirect URI.
   */
  public function redirectUri(): ?string
  {
    return $this->redirectUri;
  }

  /**
   * Method isRevoked
   * 
   * Checks if the code is revoked.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return bool True if revoked, false otherwise.
   */
  public function isRevoked(): bool
  {
    return $this->isRevoked;
  }

  /**
   * Method revoke
   * 
   * Revokes the code.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @return void
   */
  public function revoke(): void
  {
    $this->isRevoked = true;
  }
}
