<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use Intervention\Domain\ValueObject\InterventionStatus;

/** A typed merge patch for one intervention transition. */
final readonly class InterventionPatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Groups typed field changes and an optional status transition.
   *
   * @access public
   *
   * @param InterventionTextChanges $text text changes requested by the patch
   * @param InterventionOwnershipChanges $ownership ownership changes requested by the patch
   * @param InterventionScheduleChanges $schedule schedule changes requested by the patch
   * @param ?InterventionStatus $nextStatus optional next lifecycle status
   *
   * @return void
   */
  public function __construct(
    public InterventionTextChanges $text = new InterventionTextChanges(),
    public InterventionOwnershipChanges $ownership = new InterventionOwnershipChanges(),
    public InterventionScheduleChanges $schedule = new InterventionScheduleChanges(),
    public ?InterventionStatus $nextStatus = null,
  ) {
  }
  // #endregion
}
