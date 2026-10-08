<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Plan;

use Maintenance\Application\Contract\Plan\MaintenancePlanState;

/**
 * Interface MaintenanceLegacyPlanPort
 *
 * Maintains the historical schedule source and maps its live work during engine
 * handover. Callers hold the organization's synchronized main transaction.
 *
 * @category Port
 */
interface MaintenanceLegacyPlanPort
{
  // #region Methods
  /**
   * Method prepare
   *
   * Reconciles historical plans without generating work or changing authority.
   *
   * @access public
   *
   * @param string $organizationId the locked organization
   *
   * @return int the number of prepared historical schedules
   */
  public function prepare(string $organizationId): int;

  /**
   * Method activate
   *
   * Maps unambiguous open legacy work before changing persisted authority.
   * The caller refreshes projections within the same transaction afterwards.
   *
   * @access public
   *
   * @param string $organizationId the locked organization
   *
   * @return int the preparation count, or current count for an active engine
   */
  public function activate(string $organizationId): int;

  /**
   * Method refreshPolicy
   *
   * Retains the historical override/default source after handover. Open
   * occurrences keep their calendar; callers may defer saves during paging.
   *
   * @access public
   *
   * @param MaintenancePlanState $plan the candidate historical operation
   * @param bool $save whether changes are persisted immediately
   *
   * @return bool whether the plan still has an effective cadence source
   */
  public function refreshPolicy(MaintenancePlanState $plan, bool $save = true): bool;
  // #endregion
}
