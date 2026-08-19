<?php

declare(strict_types=1);

namespace Calendar\Domain\Event;

use DateTimeImmutable;

/**
 * Event CalendarEventUpdatedEvent.
 *
 * Raised when a standalone organization calendar event is updated. Mirrors
 * {@see CalendarEventCreatedEvent}'s shape — `title`/`startsAt` carry the
 * event's *current* (post-update) values, not a diff.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarEventUpdatedEvent
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
   * CalendarEventUpdatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $eventId the calendar event ID
   * @param string $title the event's current title
   * @param DateTimeImmutable $startsAt the event's current start
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $eventId,
    public string $title,
    public DateTimeImmutable $startsAt,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
