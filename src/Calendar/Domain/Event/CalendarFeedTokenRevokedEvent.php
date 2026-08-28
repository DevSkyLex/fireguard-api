<?php

declare(strict_types=1);

namespace Calendar\Domain\Event;

use DateTimeImmutable;

/**
 * Event CalendarFeedTokenRevokedEvent.
 *
 * Raised when a member's personal iCal feed token is revoked, either
 * explicitly (DELETE) or implicitly by a rotation. Identifiers only —
 * never the raw secret nor the token hash.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarFeedTokenRevokedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CalendarFeedTokenRevokedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $tokenId the feed token ID
   * @param string $actorUserId the acting (and owning) user identifier
   * @param string $reason why the token was revoked (`revoked` or `rotated`)
   */
  public function __construct(
    public string $organizationId,
    public string $tokenId,
    public string $actorUserId,
    public string $reason,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
