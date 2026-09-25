<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * Schedule overrides, including the recomputed next occurrence.
 */
final readonly class InterventionRecurrenceSchedulePatch
{
  public function __construct(
    public ?string $timezone,
    public ?int $leadTimeDays,
    public ?DateTimeImmutable $nextOccurrenceAt,
    public bool $hasTimezone,
    public bool $hasLeadTimeDays,
    public bool $hasNextOccurrenceAt,
  ) {
  }
}
