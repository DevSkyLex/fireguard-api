<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

/**
 * Enum MaintenanceLogSource.
 *
 * Identifies what produced a maintenance log entry: a status-transition
 * window (equipment entering/leaving `under_maintenance`, tracked as an
 * open/close pair) or a point-in-time service record synthesized from a
 * published intervention (`recordInterventionService()`, always completed).
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum MaintenanceLogSource: string
{
  case STATUS_TRANSITION = 'status_transition';
  case INTERVENTION = 'intervention';
}
