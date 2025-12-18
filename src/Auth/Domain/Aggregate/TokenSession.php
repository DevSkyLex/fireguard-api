<?php

declare(strict_types=1);

namespace Auth\Domain\Aggregate;

use OAuth\Domain\Event\TokenIssuedEvent;
use OAuth\Domain\Event\TokenRevokedEvent;
use Auth\Domain\Event\UserLoggedInEvent;
use Auth\Domain\Event\UserLoggedOutEvent;
use OAuth\Domain\ValueObject\TokenExpiry;
use OAuth\Domain\ValueObject\TokenIdentifier;
use DateTimeImmutable;

/**
 * Aggregate TokenSession
 * @final
 *
 * Aggregate root representing a user's authentication session.
 * Manages access tokens, refresh tokens, and their lifecycle.
 *
 * @category Aggregate
 * @package Auth\Domain\Aggregate
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenSession
{
  //#region Properties
  /**
   * Property domainEvents
   *
   * @var list<object>
   */
  private array $domainEvents = [];

  /**
   * Property isRevoked
   *
   * @var bool
   */
  private bool $isRevoked = false;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * TokenSession class.
   *
   * @access private
   * @since 1.0.0
   *
   * @param TokenIdentifier $accessTokenId The access token identifier.
   * @param TokenIdentifier|null $refreshTokenId The refresh token identifier.
   * @param string $userId The user identifier.
   * @param string $clientId The client identifier.
   * @param list<string> $scopes The granted scopes.
   * @param TokenExpiry $accessTokenExpiry The access token expiry.
   * @param TokenExpiry|null $refreshTokenExpiry The refresh token expiry.
   * @param DateTimeImmutable $createdAt The creation timestamp.
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
  ) {}
  //#endregion

  //#region Factory Methods
  /**
   * Method createForUser
   *
   * Creates a new token session for a user login.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user identifier.
   * @param string $email The user email.
   * @param list<string> $scopes The granted scopes.
   * @param int $accessTokenTtl Access token TTL in seconds.
   * @param int $refreshTokenTtl Refresh token TTL in seconds.
   * @param string|null $ipAddress The client IP address.
   *
   * @return self The token session.
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
   * Method createForClient
   *
   * Creates a new token session for client credentials.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientId The client identifier.
   * @param list<string> $scopes The granted scopes.
   * @param int $accessTokenTtl Access token TTL in seconds.
   *
   * @return self The token session.
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
  //#endregion

  //#region Methods
  /**
   * Method revoke
   *
   * Revokes the token session.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $reason The revocation reason.
   * @param string|null $ipAddress The client IP address.
   *
   * @return void
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

    if ($this->refreshTokenId !== null) {
      $this->recordEvent(new TokenRevokedEvent(
        tokenId: $this->refreshTokenId->value,
        tokenType: 'refresh_token',
        reason: $reason,
      ));
    }

    if ($this->userId !== '') {
      $this->recordEvent(new UserLoggedOutEvent(
        userId: $this->userId,
        ipAddress: $ipAddress,
        refreshTokenRevoked: $this->refreshTokenId !== null,
        accessTokenRevoked: true,
      ));
    }
  }

  /**
   * Method isExpired
   *
   * Checks if the access token is expired.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if expired.
   */
  public function isExpired(): bool
  {
    return $this->accessTokenExpiry->isExpired();
  }

  /**
   * Method isRevoked
   *
   * Checks if the session is revoked.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if revoked.
   */
  public function isRevoked(): bool
  {
    return $this->isRevoked;
  }

  /**
   * Method isValid
   *
   * Checks if the session is valid (not expired and not revoked).
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if valid.
   */
  public function isValid(): bool
  {
    return !$this->isExpired() && !$this->isRevoked;
  }

  /**
   * Method accessTokenId
   *
   * @access public
   * @since 1.0.0
   *
   * @return TokenIdentifier
   */
  public function accessTokenId(): TokenIdentifier
  {
    return $this->accessTokenId;
  }

  /**
   * Method accessTokenExpiry
   *
   * @access public
   * @since 1.0.0
   *
   * @return TokenExpiry
   */
  public function accessTokenExpiry(): TokenExpiry
  {
    return $this->accessTokenExpiry;
  }

  /**
   * Method refreshTokenExpiry
   *
   * @access public
   * @since 1.0.0
   *
   * @return TokenExpiry|null
   */
  public function refreshTokenExpiry(): ?TokenExpiry
  {
    return $this->refreshTokenExpiry;
  }

  /**
   * Method createdAt
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method refreshTokenId
   *
   * @access public
   * @since 1.0.0
   *
   * @return TokenIdentifier|null
   */
  public function refreshTokenId(): ?TokenIdentifier
  {
    return $this->refreshTokenId;
  }

  /**
   * Method userId
   *
   * @access public
   * @since 1.0.0
   *
   * @return string
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method clientId
   *
   * @access public
   * @since 1.0.0
   *
   * @return string
   */
  public function clientId(): string
  {
    return $this->clientId;
  }

  /**
   * Method scopes
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<string>
   */
  public function scopes(): array
  {
    return $this->scopes;
  }

  /**
   * Method pullDomainEvents
   *
   * Returns and clears all recorded domain events.
   *
   * @access public
   * @since 1.0.0
   *
   * @return list<object> The domain events.
   */
  public function pullDomainEvents(): array
  {
    $events = $this->domainEvents;
    $this->domainEvents = [];
    return $events;
  }

  /**
   * Method recordEvent
   *
   * Records a domain event.
   *
   * @access private
   * @since 1.0.0
   *
   * @param object $event The event.
   *
   * @return void
   */
  private function recordEvent(object $event): void
  {
    $this->domainEvents[] = $event;
  }
  //#endregion
}
