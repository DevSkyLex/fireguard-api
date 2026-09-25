<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

/**
 * A validated recurrence merge-patch without loss of field presence.
 */
final readonly class InterventionRecurrenceUpdateRequest
{
  public function __construct(
    public string $id,
    public InterventionRecurrenceIdentityPatch $identity,
    public InterventionRecurrenceCadencePatch $cadence,
    public InterventionRecurrenceSchedulePatch $schedule,
    public InterventionRecurrenceLifecyclePatch $lifecycle,
  ) {
  }
}
