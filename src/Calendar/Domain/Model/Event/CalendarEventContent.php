<?php

declare(strict_types=1);

namespace Calendar\Domain\Model\Event;

use DateTimeImmutable;

/** Event details shared by creation and persistence restoration. */
final readonly class CalendarEventContent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the editable event details and optional facility association.
   *
   * @access public
   *
   * @param string $title event title
   * @param ?string $description optional event description
   * @param DateTimeImmutable $startsAt event start instant
   * @param ?DateTimeImmutable $endsAt event end instant, when the event has one
   * @param bool $allDay whether the event spans a whole day rather than a timed interval
   * @param ?string $facilityId associated facility identifier, when selected
   *
   * @return void
   */
  public function __construct(
    public string $title,
    public ?string $description,
    public DateTimeImmutable $startsAt,
    public ?DateTimeImmutable $endsAt,
    public bool $allDay,
    public ?string $facilityId,
  ) {
  }
  // #endregion
}
