<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenancePlanState};
use Maintenance\Domain\Exception\MaintenanceValidationException;
use Maintenance\Domain\Model\{MaintenanceOccurrence, MaintenancePlan};
use Maintenance\Domain\ValueObject\{MaintenanceOccurrenceAttempt, MaintenanceOperationKind, MaintenancePlanCalendar, MaintenancePlanIdentity, PlanCadence};

use function mb_strlen;
use function trim;

/**
 * Class MaintenancePlanModelFactory
 *
 * Restores validated domain models from application snapshots without exposing
 * persistence or application contract types to the domain.
 *
 * @category Service
 */
final readonly class MaintenancePlanModelFactory
{
  // #region Methods
  /**
   * Method plan
   *
   * Retains the original calendar, engine mode and archive state during hydration.
   *
   * @access public
   *
   * @param MaintenancePlanState $plan the persisted application snapshot
   *
   * @return MaintenancePlan the validated operation
   */
  public static function plan(MaintenancePlanState $plan): MaintenancePlan
  {
    if (mb_strlen(trim($plan->name)) > 160) {
      throw new MaintenanceValidationException('A plan name cannot exceed 160 characters.');
    }
    $legacy = 'legacy' === $plan->cadenceMode;
    $cadence = $legacy ? PlanCadence::legacyFromString($plan->interval) : PlanCadence::fromString($plan->interval);

    return MaintenancePlan::reconstitute(
      new MaintenancePlanIdentity($plan->id, $plan->organizationId, $plan->equipmentId),
      $plan->name,
      MaintenanceOperationKind::from($plan->operationKind),
      new MaintenancePlanCalendar($cadence, $plan->anchorAt, $plan->nextDueAt, $legacy),
      $plan->createdAt,
      null !== $plan->archivedAt,
    );
  }

  /**
   * Method occurrence
   *
   * Restores the original reservation and the current explicit attempt/receipt.
   *
   * @access public
   *
   * @param MaintenanceOccurrenceState $occurrence the persisted application snapshot
   *
   * @return MaintenanceOccurrence the validated occurrence
   */
  public static function occurrence(MaintenanceOccurrenceState $occurrence): MaintenanceOccurrence
  {
    return MaintenanceOccurrence::reconstitute(
      $occurrence->id,
      $occurrence->planId,
      $occurrence->dueAt,
      $occurrence->createdAt,
      new MaintenanceOccurrenceAttempt($occurrence->attempt, $occurrence->interventionId, $occurrence->completedAt, $occurrence->resultId),
    );
  }
  // #endregion
}
