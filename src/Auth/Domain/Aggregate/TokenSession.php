<?php

declare(strict_types=1);

namespace Auth\Domain\Aggregate;

use Auth\Domain\Event\UserLoggedInEvent;
use Auth\Domain\Event\UserLoggedOutEvent;
use DateTimeImmutable;
use OAuth\Domain\Event\TokenIssuedEvent;
use OAuth\Domain\Event\TokenRevokedEvent;
use OAuth\Domain\ValueObject\TokenExpiry;
use OAuth\Domain\ValueObject\TokenIdentifier;

/**
 * Aggregate TokenSession.
 *
 * @category Aggregate
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenSession
{
    // #region Properties
    /**
     * Property domainEvents.
     *
     * @var list<object>
     */
    private array $domainEvents = [];

    /**
     * Property isRevoked.
     */
    private bool $isRevoked = false;
    // #endregion

    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * TokenSession class.
     *
     * @since 1.0.0
     *
     * @param TokenIdentifier      $accessTokenId      the access token identifier
     * @param TokenIdentifier|null $refreshTokenId     the refresh token identifier
     * @param string               $userId             the user identifier
     * @param string               $clientId           the client identifier
     * @param list<string>         $scopes             the granted scopes
     * @param TokenExpiry          $accessTokenExpiry  the access token expiry
     * @param TokenExpiry|null     $refreshTokenExpiry the refresh token expiry
     * @param DateTimeImmutable    $createdAt          the creation timestamp
     */
    private function __construct(
        private readonly TokenIdentifier $accessTokenId,
        private readonly ?TokenIdentifier $refreshTokenId,
        private readonly string $userId,
        private readonly string $clientId,
        private readonly array $scopes,
        private readonly TokenExpiry $accessTokenExpiry,
        private readonly ?TokenExpiry $refreshTokenExpiry,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }
    // #endregion

    // #region Factory Methods
    /**
     * Method createForUser.
     *
     * Creates a new token session for a user login.
     *
     * @since 1.0.0
     *
     * @param string       $userId          the user identifier
     * @param string       $email           the user email
     * @param list<string> $scopes          the granted scopes
     * @param int          $accessTokenTtl  access token TTL in seconds
     * @param int          $refreshTokenTtl refresh token TTL in seconds
     * @param string|null  $ipAddress       the client IP address
     *
     * @return self the token session
     */
    public static function createForUser(
        string $userId,
        string $email,
        array $scopes,
        int $accessTokenTtl = 3600,
        int $refreshTokenTtl = 86400,
        ?string $ipAddress = null,
    ): self {
        $session = new self(
            accessTokenId: TokenIdentifier::generate(),
            refreshTokenId: TokenIdentifier::generate(),
            userId: $userId,
            clientId: 'user_session',
            scopes: $scopes,
            accessTokenExpiry: TokenExpiry::fromSeconds($accessTokenTtl),
            refreshTokenExpiry: TokenExpiry::fromSeconds($refreshTokenTtl),
            createdAt: new DateTimeImmutable(),
        );

        $session->recordEvent(new UserLoggedInEvent(
            userId: $userId,
            email: $email,
            ipAddress: $ipAddress,
        ));

        $session->recordEvent(new TokenIssuedEvent(
            tokenId: $session->accessTokenId->value,
            grantType: 'password',
            clientId: 'user_session',
            userId: $userId,
            scopes: $scopes,
            expiresIn: $accessTokenTtl,
        ));

        return $session;
    }

    /**
     * Method createForClient.
     *
     * Creates a new token session for client credentials.
     *
     * @since 1.0.0
     *
     * @param string       $clientId       the client identifier
     * @param list<string> $scopes         the granted scopes
     * @param int          $accessTokenTtl access token TTL in seconds
     *
     * @return self the token session
     */
    public static function createForClient(
        string $clientId,
        array $scopes,
        int $accessTokenTtl = 3600,
    ): self {
        $session = new self(
            accessTokenId: TokenIdentifier::generate(),
            refreshTokenId: null,
            userId: '',
            clientId: $clientId,
            scopes: $scopes,
            accessTokenExpiry: TokenExpiry::fromSeconds($accessTokenTtl),
            refreshTokenExpiry: null,
            createdAt: new DateTimeImmutable(),
        );

        $session->recordEvent(new TokenIssuedEvent(
            tokenId: $session->accessTokenId->value,
            grantType: 'client_credentials',
            clientId: $clientId,
            scopes: $scopes,
            expiresIn: $accessTokenTtl,
        ));

        return $session;
    }
    // #endregion

    // #region Methods
    /**
     * Method revoke.
     *
     * Revokes the token session.
     *
     * @since 1.0.0
     *
     * @param string|null $reason    the revocation reason
     * @param string|null $ipAddress the client IP address
     */
    public function revoke(?string $reason = null, ?string $ipAddress = null): void
    {
        if ($this->isRevoked) {
            return;
        }

        $this->isRevoked = true;

        $this->recordEvent(new TokenRevokedEvent(
            tokenId: $this->accessTokenId->value,
            tokenType: 'access_token',
            reason: $reason,
        ));

        if (null !== $this->refreshTokenId) {
            $this->recordEvent(new TokenRevokedEvent(
                tokenId: $this->refreshTokenId->value,
                tokenType: 'refresh_token',
                reason: $reason,
            ));
        }

        if ('' !== $this->userId) {
            $this->recordEvent(new UserLoggedOutEvent(
                userId: $this->userId,
                ipAddress: $ipAddress,
                refreshTokenRevoked: null !== $this->refreshTokenId,
                accessTokenRevoked: true,
            ));
        }
    }

    /**
     * Method isExpired.
     *
     * Checks if the access token is expired.
     *
     * @since 1.0.0
     *
     * @return bool true if expired
     */
    public function isExpired(): bool
    {
        return $this->accessTokenExpiry->isExpired();
    }

    /**
     * Method isRevoked.
     *
     * Checks if the session is revoked.
     *
     * @since 1.0.0
     *
     * @return bool true if revoked
     */
    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    /**
     * Method isValid.
     *
     * Checks if the session is valid (not expired and not revoked).
     *
     * @since 1.0.0
     *
     * @return bool true if valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked;
    }

    /**
     * Method accessTokenId.
     *
     * @since 1.0.0
     */
    public function accessTokenId(): TokenIdentifier
    {
        return $this->accessTokenId;
    }

    /**
     * Method accessTokenExpiry.
     *
     * @since 1.0.0
     */
    public function accessTokenExpiry(): TokenExpiry
    {
        return $this->accessTokenExpiry;
    }

    /**
     * Method refreshTokenExpiry.
     *
     * @since 1.0.0
     */
    public function refreshTokenExpiry(): ?TokenExpiry
    {
        return $this->refreshTokenExpiry;
    }

    /**
     * Method createdAt.
     *
     * @since 1.0.0
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Method refreshTokenId.
     *
     * @since 1.0.0
     */
    public function refreshTokenId(): ?TokenIdentifier
    {
        return $this->refreshTokenId;
    }

    /**
     * Method userId.
     *
     * @since 1.0.0
     */
    public function userId(): string
    {
        return $this->userId;
    }

    /**
     * Method clientId.
     *
     * @since 1.0.0
     */
    public function clientId(): string
    {
        return $this->clientId;
    }

    /**
     * Method scopes.
     *
     * @since 1.0.0
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Method pullDomainEvents.
     *
     * Returns and clears all recorded domain events.
     *
     * @since 1.0.0
     *
     * @return list<object> the domain events
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Method recordEvent.
     *
     * Records a domain event.
     *
     * @since 1.0.0
     *
     * @param object $event the event
     */
    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
    // #endregion
}
