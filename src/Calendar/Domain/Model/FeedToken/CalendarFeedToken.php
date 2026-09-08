<?php

declare(strict_types=1);

namespace Calendar\Domain\Model\FeedToken;

use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use DateTimeImmutable;

/**
 * Model CalendarFeedToken.
 *
 * A member-scoped iCal subscription token: one active token per
 * (organization, user) pair, storing only the SHA-256 hash of the secret —
 * the raw secret exists in memory once, at creation, and is shown to the
 * member exactly once. `lastUsedAt` is a coarse usage marker throttled to
 * one write per hour (see {@see self::shouldRecordUsage()}) so calendar
 * clients polling the feed do not hammer the database.
 *
 * `organizationId` and `userId` are plain strings, mirroring
 * {@see \Calendar\Domain\Model\Event\CalendarEvent}: this module never
 * depends on the Organization or User ORM mappings.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedToken
{
  // #region Constants
  /**
   * Constant USAGE_WRITE_THROTTLE_SECONDS.
   *
   * Minimum interval between two persisted `lastUsedAt` updates.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int USAGE_WRITE_THROTTLE_SECONDS = 3600;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CalendarFeedToken class.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenId $id the token identifier
   * @param string $organizationId the owning organization identifier
   * @param string $userId the owning user identifier
   * @param string $tokenHash the SHA-256 hash of the secret (never the secret itself)
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param ?DateTimeImmutable $lastUsedAt the last recorded feed fetch, when any
   * @param ?DateTimeImmutable $revokedAt the revocation timestamp, when revoked
   */
  private function __construct(
    private CalendarFeedTokenId $id,
    private string $organizationId,
    private string $userId,
    private string $tokenHash,
    private DateTimeImmutable $createdAt,
    private ?DateTimeImmutable $lastUsedAt,
    private ?DateTimeImmutable $revokedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new active feed token from an already-hashed secret.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenId $id the token identifier
   * @param string $organizationId the owning organization identifier
   * @param string $userId the owning user identifier
   * @param string $tokenHash the SHA-256 hash of the secret
   *
   * @return self the created feed token
   */
  public static function create(
    CalendarFeedTokenId $id,
    string $organizationId,
    string $userId,
    string $tokenHash,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      userId: $userId,
      tokenHash: $tokenHash,
      createdAt: new DateTimeImmutable(),
      lastUsedAt: null,
      revokedAt: null,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a feed token aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenId $id the token identifier
   * @param string $organizationId the owning organization identifier
   * @param string $userId the owning user identifier
   * @param string $tokenHash the SHA-256 hash of the secret
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param ?DateTimeImmutable $lastUsedAt the last recorded feed fetch, when any
   * @param ?DateTimeImmutable $revokedAt the revocation timestamp, when revoked
   *
   * @return self the reconstituted feed token
   */
  public static function reconstitute(
    CalendarFeedTokenId $id,
    string $organizationId,
    string $userId,
    string $tokenHash,
    DateTimeImmutable $createdAt,
    ?DateTimeImmutable $lastUsedAt,
    ?DateTimeImmutable $revokedAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      userId: $userId,
      tokenHash: $tokenHash,
      createdAt: $createdAt,
      lastUsedAt: $lastUsedAt,
      revokedAt: $revokedAt,
    );
  }

  /**
   * Method revoke.
   *
   * Marks the token revoked. Idempotent: revoking an already-revoked token
   * keeps the original revocation timestamp.
   *
   * @since 1.0.0
   */
  public function revoke(): void
  {
    if (null === $this->revokedAt) {
      $this->revokedAt = new DateTimeImmutable();
    }
  }

  /**
   * Method isRevoked.
   *
   * @since 1.0.0
   *
   * @return bool whether the token has been revoked
   */
  public function isRevoked(): bool
  {
    return null !== $this->revokedAt;
  }

  /**
   * Method shouldRecordUsage.
   *
   * Whether a feed fetch at `$now` warrants persisting `lastUsedAt`: only
   * when no usage was ever recorded, or the last record is at least one
   * hour old.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the fetch timestamp
   *
   * @return bool whether `lastUsedAt` should be persisted
   */
  public function shouldRecordUsage(DateTimeImmutable $now): bool
  {
    return null === $this->lastUsedAt
      || $now->getTimestamp() - $this->lastUsedAt->getTimestamp() >= self::USAGE_WRITE_THROTTLE_SECONDS;
  }

  /**
   * Method recordUsage.
   *
   * Records a feed fetch timestamp.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the fetch timestamp
   */
  public function recordUsage(DateTimeImmutable $now): void
  {
    $this->lastUsedAt = $now;
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return CalendarFeedTokenId the token identifier
   */
  public function id(): CalendarFeedTokenId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @return string the owning organization identifier
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method userId.
   *
   * @since 1.0.0
   *
   * @return string the owning user identifier
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method tokenHash.
   *
   * @since 1.0.0
   *
   * @return string the SHA-256 hash of the secret
   */
  public function tokenHash(): string
  {
    return $this->tokenHash;
  }

  /**
   * Method createdAt.
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
   * Method lastUsedAt.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the last recorded feed fetch, when any
   */
  public function lastUsedAt(): ?DateTimeImmutable
  {
    return $this->lastUsedAt;
  }

  /**
   * Method revokedAt.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the revocation timestamp, when revoked
   */
  public function revokedAt(): ?DateTimeImmutable
  {
    return $this->revokedAt;
  }
  // #endregion
}
