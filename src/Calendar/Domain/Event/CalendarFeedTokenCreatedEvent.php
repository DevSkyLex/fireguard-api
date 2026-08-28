<?php

declare(strict_types=1);

namespace Calendar\Domain\Event;

use DateTimeImmutable;

/**
 * Event CalendarFeedTokenCreatedEvent.
 *
 * Raised when a member creates (or rotates to) a new personal iCal feed
 * token. Carries identifiers only — never the raw secret, and never the
 * token hash: the audit ledger must not hold anything that shortens a
 * brute-force of the feed URL.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarFeedTokenCreatedEvent
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
   * CalendarFeedTokenCreatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $tokenId the feed token ID
   * @param string $actorUserId the acting (and owning) user identifier
   * @param bool $rotated whether this creation replaced a previously active token
   */
  public function __construct(
    public string $organizationId,
    public string $tokenId,
    public string $actorUserId,
    public bool $rotated,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
