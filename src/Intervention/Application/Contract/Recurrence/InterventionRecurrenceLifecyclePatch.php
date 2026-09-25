<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * End date and activation overrides, with independent presence flags.
 */
final readonly class InterventionRecurrenceLifecyclePatch
{
  public function __construct(
    public ?DateTimeImmutable $endAt,
    public ?bool $isActive,
    public bool $hasEndAt,
    public bool $hasIsActive,
  ) {
  }
}
