<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use Intervention\Domain\ValueObject\InterventionStatus;

/** A typed merge patch for one intervention transition. */
final readonly class InterventionPatch
{
  public function __construct(
    public InterventionTextChanges $text = new InterventionTextChanges(),
    public InterventionOwnershipChanges $ownership = new InterventionOwnershipChanges(),
    public InterventionScheduleChanges $schedule = new InterventionScheduleChanges(),
    public ?InterventionStatus $nextStatus = null,
  ) {
  }
}
