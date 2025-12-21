<?php

declare(strict_types=1);

namespace Session\Domain\Model;

use DateTimeImmutable;
use Session\Domain\ValueObject\SessionId;
use Session\Domain\ValueObject\SessionMetadata;
use Shared\Domain\ValueObject\IpAddress;
use Shared\Domain\ValueObject\UserAgent;

/**
 * Model Session.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Session
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SessionId $id the session ID
   * @param string $userId the user ID
   * @param string|null $accessTokenId the current access token ID
   * @param string|null $refreshTokenId the current refresh token ID
   * @param IpAddress $ipAddress the client IP address
   * @param UserAgent $userAgent the client user agent
   * @param SessionMetadata $metadata additional session metadata
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $lastActivityAt the last activity timestamp
   * @param DateTimeImmutable|null $revokedAt the revocation timestamp
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
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new session.
   *
   * @since 1.0.0
   *
   * @param SessionId $id the session ID
   * @param string $userId the user ID
   * @param IpAddress $ipAddress the client IP address
   * @param UserAgent $userAgent the client user agent
   *
   * @return self the new Session instance
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
   * Method id.
   *
   * Returns the session ID.
   *
   * @since 1.0.0
   *
   * @return SessionId the session ID
   */
  public function id(): SessionId
  {
    return $this->id;
  }

  /**
   * Method userId.
   *
   * Returns the user ID.
   *
   * @since 1.0.0
   *
   * @return string the user ID
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method accessTokenId.
   *
   * Returns the current access token ID.
   *
   * @since 1.0.0
   *
   * @return string|null the access token ID
   */
  public function accessTokenId(): ?string
  {
    return $this->accessTokenId;
  }

  /**
   * Method refreshTokenId.
   *
   * Returns the current refresh token ID.
   *
   * @since 1.0.0
   *
   * @return string|null the refresh token ID
   */
  public function refreshTokenId(): ?string
  {
    return $this->refreshTokenId;
  }

  /**
   * Method ipAddress.
   *
   * Returns the client IP address.
   *
   * @since 1.0.0
   *
   * @return IpAddress the IP address
   */
  public function ipAddress(): IpAddress
  {
    return $this->ipAddress;
  }

  /**
   * Method userAgent.
   *
   * Returns the client user agent.
   *
   * @since 1.0.0
   *
   * @return UserAgent the user agent
   */
  public function userAgent(): UserAgent
  {
    return $this->userAgent;
  }

  /**
   * Method metadata.
   *
   * Returns the session metadata.
   *
   * @since 1.0.0
   *
   * @return SessionMetadata the metadata
   */
  public function metadata(): SessionMetadata
  {
    return $this->metadata;
  }

  /**
   * Method createdAt.
   *
   * Returns the creation timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method lastActivityAt.
   *
   * Returns the last activity timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the last activity timestamp
   */
  public function lastActivityAt(): DateTimeImmutable
  {
    return $this->lastActivityAt;
  }

  /**
   * Method isRevoked.
   *
   * Returns whether the session is revoked.
   *
   * @since 1.0.0
   *
   * @return bool true if revoked, false otherwise
   */
  public function isRevoked(): bool
  {
    return null !== $this->revokedAt;
  }

  /**
   * Method revokedAt.
   *
   * Returns the revocation timestamp.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null the revocation timestamp
   */
  public function revokedAt(): ?DateTimeImmutable
  {
    return $this->revokedAt;
  }

  /**
   * Method updateTokens.
   *
   * Updates the session tokens.
   *
   * @since 1.0.0
   *
   * @param string $accessTokenId the new access token ID
   * @param string $refreshTokenId the new refresh token ID
   */
  public function updateTokens(string $accessTokenId, string $refreshTokenId): void
  {
    $this->accessTokenId = $accessTokenId;
    $this->refreshTokenId = $refreshTokenId;
    $this->lastActivityAt = new DateTimeImmutable();
  }

  /**
   * Method touch.
   *
   * Updates the last activity timestamp.
   *
   * @since 1.0.0
   */
  public function touch(): void
  {
    $this->lastActivityAt = new DateTimeImmutable();
  }

  /**
   * Method revoke.
   *
   * Revokes the session.
   *
   * @since 1.0.0
   */
  public function revoke(): void
  {
    if ($this->isRevoked()) {
      return;
    }

    $this->revokedAt = new DateTimeImmutable();
  }
  // #endregion
}
