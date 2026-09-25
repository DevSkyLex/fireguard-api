<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * The persisted schedule of a newly created recurrence.
 */
final readonly class InterventionRecurrenceSchedule
{
  public function __construct(
    public string $frequency,
    public int $interval,
    public DateTimeImmutable $anchorDate,
    public string $timezone,
    public int $leadTimeDays,
    public DateTimeImmutable $nextOccurrenceAt,
    public ?DateTimeImmutable $endAt,
  ) {
  }
}
