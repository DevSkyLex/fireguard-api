<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\InterventionPriority;

/** Priority and operating window of an intervention. */
final readonly class InterventionSchedule
{
  public function __construct(
    public InterventionPriority $priority,
    public ?DateTimeImmutable $plannedStartAt,
    public ?DateTimeImmutable $dueAt,
  ) {
  }
}
