<?php

declare(strict_types=1);

namespace OAuth\Domain\Model;

use DateTimeImmutable;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scopes;

/**
 * Model AccessToken
 * @final
 *
 * Represents an OAuth 2.0 access token.
 *
 * @category Model
 * @package OAuth\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AccessToken
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * AccessToken class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $identifier The unique identifier of the token.
   * @param OAuthClientIdentifier $clientIdentifier The client identifier.
   * @param DateTimeImmutable $expiry The expiry date and time.
   * @param Scopes $scopes The scopes associated with the token.
   * @param string|null $userIdentifier The user identifier (if applicable).
   * @param bool $isRevoked Whether the token is revoked.
   */
  public function __construct(
    private readonly string $identifier,
    private readonly OAuthClientIdentifier $clientIdentifier,
    private readonly DateTimeImmutable $expiry,
    private readonly Scopes $scopes,
    private readonly ?string $userIdentifier = null,
    private bool $isRevoked = false
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method identifier
   *
   * Returns the token identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The token identifier.
   */
  public function identifier(): string
  {
    return $this->identifier;
  }

  /**
   * Method clientIdentifier
   *
   * Returns the client identifier.
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
   * Method expiry
   *
   * Returns the expiry date.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The expiry date.
   */
  public function expiry(): DateTimeImmutable
  {
    return $this->expiry;
  }

  /**
   * Method scopes
   *
   * Returns the scopes.
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
   * Method userIdentifier
   *
   * Returns the user identifier.
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
   * Method isRevoked
   *
   * Returns whether the token is revoked.
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
   * Revokes the token.
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

  /**
   * Method isExpired
   *
   * Checks if the token is expired.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if expired, false otherwise.
   */
  public function isExpired(): bool
  {
    return $this->expiry < new DateTimeImmutable();
  }
  //#endregion
}
