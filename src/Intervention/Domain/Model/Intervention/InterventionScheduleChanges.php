<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\InterventionPriority;

/** Planning edits with explicit presence flags. */
final readonly class InterventionScheduleChanges
{
  public function __construct(
    public ?InterventionPriority $priority = null,
    public ?DateTimeImmutable $plannedStartAt = null,
    public ?DateTimeImmutable $dueAt = null,
    public bool $hasPriority = false,
    public bool $hasPlannedStartAt = false,
    public bool $hasDueAt = false,
  ) {
  }
}
