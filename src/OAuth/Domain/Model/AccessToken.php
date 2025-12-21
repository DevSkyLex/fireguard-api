<?php

declare(strict_types=1);

namespace OAuth\Domain\Model;

use DateTimeImmutable;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scopes;

/**
 * Model AccessToken.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AccessToken
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AccessToken class.
   *
   * @since 1.0.0
   *
   * @param string                $identifier       the unique identifier of the token
   * @param OAuthClientIdentifier $clientIdentifier the client identifier
   * @param DateTimeImmutable     $expiry           the expiry date and time
   * @param Scopes                $scopes           the scopes associated with the token
   * @param string|null           $userIdentifier   the user identifier (if applicable)
   * @param bool                  $isRevoked        whether the token is revoked
   */
  public function __construct(
    private readonly string $identifier,
    private readonly OAuthClientIdentifier $clientIdentifier,
    private readonly DateTimeImmutable $expiry,
    private readonly Scopes $scopes,
    private readonly ?string $userIdentifier = null,
    private bool $isRevoked = false,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method identifier.
   *
   * Returns the token identifier.
   *
   * @since 1.0.0
   *
   * @return string the token identifier
   */
  public function identifier(): string
  {
    return $this->identifier;
  }

  /**
   * Method clientIdentifier.
   *
   * Returns the client identifier.
   *
   * @since 1.0.0
   *
   * @return OAuthClientIdentifier the client identifier
   */
  public function clientIdentifier(): OAuthClientIdentifier
  {
    return $this->clientIdentifier;
  }

  /**
   * Method expiry.
   *
   * Returns the expiry date.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the expiry date
   */
  public function expiry(): DateTimeImmutable
  {
    return $this->expiry;
  }

  /**
   * Method scopes.
   *
   * Returns the scopes.
   *
   * @since 1.0.0
   *
   * @return Scopes the scopes
   */
  public function scopes(): Scopes
  {
    return $this->scopes;
  }

  /**
   * Method userIdentifier.
   *
   * Returns the user identifier.
   *
   * @since 1.0.0
   *
   * @return string|null the user identifier
   */
  public function userIdentifier(): ?string
  {
    return $this->userIdentifier;
  }

  /**
   * Method isRevoked.
   *
   * Returns whether the token is revoked.
   *
   * @since 1.0.0
   *
   * @return bool true if revoked, false otherwise
   */
  public function isRevoked(): bool
  {
    return $this->isRevoked;
  }

  /**
   * Method revoke.
   *
   * Revokes the token.
   *
   * @since 1.0.0
   */
  public function revoke(): void
  {
    $this->isRevoked = true;
  }

  /**
   * Method isExpired.
   *
   * Checks if the token is expired.
   *
   * @since 1.0.0
   *
   * @return bool true if expired, false otherwise
   */
  public function isExpired(): bool
  {
    return $this->expiry < new DateTimeImmutable();
  }
  // #endregion
}
