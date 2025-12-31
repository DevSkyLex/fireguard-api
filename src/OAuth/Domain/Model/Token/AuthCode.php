<?php

declare(strict_types=1);

namespace OAuth\Domain\Model\Token;

use DateTimeImmutable;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;

/**
 * Class AuthCode.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthCode
{
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the AuthCode class.
   *
   * @since 1.0.0
   *
   * @param string $identifier the auth code identifier
   * @param DateTimeImmutable $expiryDateTime the expiry date time
   * @param OAuthClientIdentifier $clientIdentifier the client identifier
   * @param string|null $userIdentifier the user identifier
   * @param Scopes $scopes the scopes
   * @param string|null $redirectUri the redirect URI
   * @param string|null $nonce the OIDC nonce
   * @param bool $isRevoked whether the code is revoked
   */
  public function __construct(
    private readonly string $identifier,
    private readonly DateTimeImmutable $expiryDateTime,
    private readonly OAuthClientIdentifier $clientIdentifier,
    private readonly ?string $userIdentifier,
    private readonly Scopes $scopes,
    private readonly ?string $redirectUri,
    private readonly ?string $nonce = null,
    private bool $isRevoked = false,
  ) {
  }

  /**
   * Method identifier.
   *
   * Gets the auth code identifier.
   *
   * @since 1.0.0
   *
   * @return string the identifier
   */
  public function identifier(): string
  {
    return $this->identifier;
  }

  /**
   * Method expiryDateTime.
   *
   * Gets the expiry date time.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the expiry date time
   */
  public function expiryDateTime(): DateTimeImmutable
  {
    return $this->expiryDateTime;
  }

  /**
   * Method clientIdentifier.
   *
   * Gets the client identifier.
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
   * Method userIdentifier.
   *
   * Gets the user identifier.
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
   * Method scopes.
   *
   * Gets the scopes.
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
   * Method redirectUri.
   *
   * Gets the redirect URI.
   *
   * @since 1.0.0
   *
   * @return string|null the redirect URI
   */
  public function redirectUri(): ?string
  {
    return $this->redirectUri;
  }

  /**
   * Method nonce.
   *
   * Gets the OIDC nonce value.
   *
   * @since 1.0.0
   *
   * @return string|null the nonce value
   */
  public function nonce(): ?string
  {
    return $this->nonce;
  }

  /**
   * Method isRevoked.
   *
   * Checks if the code is revoked.
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
   * Revokes the code.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  public function revoke(): void
  {
    $this->isRevoked = true;
  }
}
