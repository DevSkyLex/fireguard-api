<?php

declare(strict_types=1);

namespace Calendar\Domain\Model\Event;

use DateTimeImmutable;

/** Event details shared by creation and persistence restoration. */
final readonly class CalendarEventContent
{
  public function __construct(
    public string $title,
    public ?string $description,
    public DateTimeImmutable $startsAt,
    public ?DateTimeImmutable $endsAt,
    public bool $allDay,
    public ?string $facilityId,
  ) {
  }
}
