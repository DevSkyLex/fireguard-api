<?php

declare(strict_types=1);

namespace Session\Domain\Model;

use DateTimeImmutable;
use Session\Domain\ValueObject\SessionId;
use Session\Domain\ValueObject\SessionMetadata;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;

/**
 * Model Session
 * @final
 *
 * Represents a user session with associated tokens.
 * Tracks user authentication sessions across devices.
 *
 * @category Model
 * @package Session\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Session
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access private
   * @since 1.0.0
   *
   * @param SessionId $id The session ID.
   * @param string $userId The user ID.
   * @param string|null $accessTokenId The current access token ID.
   * @param string|null $refreshTokenId The current refresh token ID.
   * @param IpAddress $ipAddress The client IP address.
   * @param UserAgent $userAgent The client user agent.
   * @param SessionMetadata $metadata Additional session metadata.
   * @param DateTimeImmutable $createdAt The creation timestamp.
   * @param DateTimeImmutable $lastActivityAt The last activity timestamp.
   * @param DateTimeImmutable|null $revokedAt The revocation timestamp.
   */
  private function __construct(
    private SessionId $id,
    private string $userId,
    private ?string $accessTokenId,
    private ?string $refreshTokenId,
    private IpAddress $ipAddress,
    private UserAgent $userAgent,
    private SessionMetadata $metadata,
    private DateTimeImmutable $createdAt,
    private DateTimeImmutable $lastActivityAt,
    private ?DateTimeImmutable $revokedAt = null,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method create
   * @static
   *
   * Creates a new session.
   *
   * @access public
   * @since 1.0.0
   *
   * @param SessionId $id The session ID.
   * @param string $userId The user ID.
   * @param IpAddress $ipAddress The client IP address.
   * @param UserAgent $userAgent The client user agent.
   *
   * @return self The new Session instance.
   */
  public static function create(
    SessionId $id,
    string $userId,
    IpAddress $ipAddress,
    UserAgent $userAgent,
  ): self {
    $now = new DateTimeImmutable();

    return new self(
      id: $id,
      userId: $userId,
      accessTokenId: null,
      refreshTokenId: null,
      ipAddress: $ipAddress,
      userAgent: $userAgent,
      metadata: new SessionMetadata(),
      createdAt: $now,
      lastActivityAt: $now,
    );
  }

  /**
   * Method id
   *
   * Returns the session ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return SessionId The session ID.
   */
  public function id(): SessionId
  {
    return $this->id;
  }

  /**
   * Method userId
   *
   * Returns the user ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The user ID.
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method accessTokenId
   *
   * Returns the current access token ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The access token ID.
   */
  public function accessTokenId(): ?string
  {
    return $this->accessTokenId;
  }

  /**
   * Method refreshTokenId
   *
   * Returns the current refresh token ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The refresh token ID.
   */
  public function refreshTokenId(): ?string
  {
    return $this->refreshTokenId;
  }

  /**
   * Method ipAddress
   *
   * Returns the client IP address.
   *
   * @access public
   * @since 1.0.0
   *
   * @return IpAddress The IP address.
   */
  public function ipAddress(): IpAddress
  {
    return $this->ipAddress;
  }

  /**
   * Method userAgent
   *
   * Returns the client user agent.
   *
   * @access public
   * @since 1.0.0
   *
   * @return UserAgent The user agent.
   */
  public function userAgent(): UserAgent
  {
    return $this->userAgent;
  }

  /**
   * Method metadata
   *
   * Returns the session metadata.
   *
   * @access public
   * @since 1.0.0
   *
   * @return SessionMetadata The metadata.
   */
  public function metadata(): SessionMetadata
  {
    return $this->metadata;
  }

  /**
   * Method createdAt
   *
   * Returns the creation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The creation timestamp.
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method lastActivityAt
   *
   * Returns the last activity timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The last activity timestamp.
   */
  public function lastActivityAt(): DateTimeImmutable
  {
    return $this->lastActivityAt;
  }

  /**
   * Method isRevoked
   *
   * Returns whether the session is revoked.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if revoked, false otherwise.
   */
  public function isRevoked(): bool
  {
    return $this->revokedAt !== null;
  }

  /**
   * Method revokedAt
   *
   * Returns the revocation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null The revocation timestamp.
   */
  public function revokedAt(): ?DateTimeImmutable
  {
    return $this->revokedAt;
  }

  /**
   * Method updateTokens
   *
   * Updates the session tokens.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $accessTokenId The new access token ID.
   * @param string $refreshTokenId The new refresh token ID.
   *
   * @return void
   */
  public function updateTokens(string $accessTokenId, string $refreshTokenId): void
  {
    $this->accessTokenId = $accessTokenId;
    $this->refreshTokenId = $refreshTokenId;
    $this->lastActivityAt = new DateTimeImmutable();
  }

  /**
   * Method touch
   *
   * Updates the last activity timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void
   */
  public function touch(): void
  {
    $this->lastActivityAt = new DateTimeImmutable();
  }

  /**
   * Method revoke
   *
   * Revokes the session.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void
   */
  public function revoke(): void
  {
    if ($this->isRevoked()) {
      return;
    }

    $this->revokedAt = new DateTimeImmutable();
  }
  //#endregion
}
