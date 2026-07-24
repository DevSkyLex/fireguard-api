<?php

declare(strict_types=1);

namespace Billing\Application\Port\Outbound;

/**
 * Port OrganizationPlanAssignmentPort.
 *
 * Outbound contract the Billing module uses to apply a plan change to an
 * organization once a payment is confirmed. The Organization module provides the
 * adapter; Billing never touches the organization aggregate directly.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationPlanAssignmentPort
{
  // #region Methods
  /**
   * Method assignPlanByKey.
   *
   * Assigns the organization to the plan identified by its stable key.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $planKey the target plan key (e.g. free, pro, max)
   */
  public function assignPlanByKey(string $organizationId, string $planKey): void;
  // #endregion
}
