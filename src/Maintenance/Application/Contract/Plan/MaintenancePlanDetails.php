<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Plan;

/** Read model combines a plan with its current occurrence and server retry decision. */
final readonly class MaintenancePlanDetails
{
  public function __construct(public MaintenancePlanState $plan, public ?MaintenanceOccurrenceState $openOccurrence, public bool $retryAllowed = false)
  {
  }
}
