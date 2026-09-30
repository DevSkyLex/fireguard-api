<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

/**
 * A validated recurrence merge-patch without loss of field presence.
 */
final readonly class InterventionRecurrenceUpdateRequest
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Groups validated recurrence patch sections while preserving field presence.
   *
   * @access public
   *
   * @param string $id recurrence identifier to update
   * @param InterventionRecurrenceIdentityPatch $identity identity fields supplied by the patch
   * @param InterventionRecurrenceCadencePatch $cadence frequency rule fields supplied by the patch
   * @param InterventionRecurrenceSchedulePatch $schedule schedule fields supplied by the patch
   * @param InterventionRecurrenceLifecyclePatch $lifecycle lifecycle fields supplied by the patch
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public InterventionRecurrenceIdentityPatch $identity,
    public InterventionRecurrenceCadencePatch $cadence,
    public InterventionRecurrenceSchedulePatch $schedule,
    public InterventionRecurrenceLifecyclePatch $lifecycle,
  ) {
  }
  // #endregion
}
