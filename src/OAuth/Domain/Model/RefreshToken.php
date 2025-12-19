<?php

declare(strict_types=1);

namespace OAuth\Domain\Model;

use DateTimeImmutable;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Class RefreshToken.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RefreshToken
{
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * RefreshToken class.
     *
     * @since 1.0.0
     *
     * @param string                $identifier            the refresh token identifier
     * @param DateTimeImmutable     $expiryDateTime        the expiry date time
     * @param string                $accessTokenIdentifier the access token identifier
     * @param OAuthClientIdentifier $clientIdentifier      the client identifier
     * @param bool                  $isRevoked             whether the token is revoked
     */
    public function __construct(
        private readonly string $identifier,
        private readonly DateTimeImmutable $expiryDateTime,
        private readonly string $accessTokenIdentifier,
        private readonly OAuthClientIdentifier $clientIdentifier,
        private bool $isRevoked = false,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method identifier.
     *
     * Gets the refresh token identifier.
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
     * Method accessTokenIdentifier.
     *
     * Gets the access token identifier.
     *
     * @since 1.0.0
     *
     * @return string the access token identifier
     */
    public function accessTokenIdentifier(): string
    {
        return $this->accessTokenIdentifier;
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
     * Method isRevoked.
     *
     * Checks if the token is revoked.
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
        return $this->expiryDateTime < new DateTimeImmutable();
    }
}
