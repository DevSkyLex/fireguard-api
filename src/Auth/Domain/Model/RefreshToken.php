<?php

declare(strict_types=1);

namespace Auth\Domain\Model;

use DateTimeImmutable;
use Shared\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Class RefreshToken
 * @final
 *
 * Domain model for OAuth2 Refresh Token.
 *
 * @category Model
 * @package Auth\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshToken
{
  /**
   * Constructor
   *
   * Initializes a new instance of the RefreshToken class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $identifier The refresh token identifier.
   * @param DateTimeImmutable $expiryDateTime The expiry date time.
   * @param string $accessTokenIdentifier The access token identifier.
   * @param OAuthClientIdentifier $clientIdentifier The client identifier.
   * @param bool $isRevoked Whether the token is revoked.
   */
  public function __construct(
    private readonly string $identifier,
    private readonly DateTimeImmutable $expiryDateTime,
    private readonly string $accessTokenIdentifier,
    private readonly OAuthClientIdentifier $clientIdentifier,
    private bool $isRevoked = false
  ) {
  }

  /**
   * Method identifier
   *
   * Gets the refresh token identifier.
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
   * Method accessTokenIdentifier
   *
   * Gets the access token identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The access token identifier.
   */
  public function accessTokenIdentifier(): string
  {
    return $this->accessTokenIdentifier;
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
   * Method isRevoked
   *
   * Checks if the token is revoked.
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
    return $this->expiryDateTime < new DateTimeImmutable();
  }
}
