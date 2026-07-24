<?php

declare(strict_types=1);

namespace Calendar\Domain\Event;

use DateTimeImmutable;

/**
 * Event CalendarEventDeletedEvent.
 *
 * Raised when a standalone organization calendar event is deleted.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarEventDeletedEvent
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
   * CalendarEventDeletedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $eventId the calendar event ID
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $eventId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
