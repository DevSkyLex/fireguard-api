<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

/** Read model combines a plan with its current occurrence and server retry decision. */
final readonly class MaintenancePlanDetails
{
  public MaintenancePlanSnapshot $plan;

  public ?MaintenanceOccurrenceSnapshot $openOccurrence;

  /**
   * @param MaintenancePlanState|MaintenancePlanSnapshot $plan owner persistence state or decoded immutable response
   * @param MaintenanceOccurrenceState|MaintenanceOccurrenceSnapshot|null $openOccurrence current occurrence state or decoded response
   * @param bool $retryAllowed owner decision at the response commit
   */
  public function __construct(MaintenancePlanState|MaintenancePlanSnapshot $plan, MaintenanceOccurrenceState|MaintenanceOccurrenceSnapshot|null $openOccurrence, public bool $retryAllowed = false)
  {
    $this->plan = $plan instanceof MaintenancePlanSnapshot ? $plan : MaintenancePlanSnapshot::fromState($plan);
    $this->openOccurrence = null === $openOccurrence || $openOccurrence instanceof MaintenanceOccurrenceSnapshot ? $openOccurrence : MaintenanceOccurrenceSnapshot::fromState($openOccurrence);
  }
}
