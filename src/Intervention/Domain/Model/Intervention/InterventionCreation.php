<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use Intervention\Domain\ValueObject\InterventionType;

/** Data supplied to create or restore an intervention. */
final readonly class InterventionCreation
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Groups the identity, ownership and schedule needed to create an intervention.
   *
   * @access public
   *
   * @param string $id intervention identifier
   * @param string $organizationId organization that owns the intervention
   * @param InterventionType $type type of intervention
   * @param InterventionContent $content name and description of the intervention
   * @param InterventionOwnership $ownership site and responsible-member assignments
   * @param InterventionSchedule $schedule planned dates and scheduling details
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public InterventionType $type,
    public InterventionContent $content,
    public InterventionOwnership $ownership,
    public InterventionSchedule $schedule,
  ) {
  }
  // #endregion
}
