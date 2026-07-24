<?php

declare(strict_types=1);

namespace Billing\Application\Port\Outbound;

use Billing\Application\Contract\Plan\PlanSummary;

/**
 * Port OrganizationPlanPort.
 *
 * Outbound contract the Billing module uses to resolve an organization's
 * CURRENTLY assigned plan (key + display name) directly from the
 * Organization module's own canonical assignment, so the subscription read
 * model stays correct even for organizations with no local
 * `billing_subscriptions` row (e.g. an organization that never went through
 * Checkout and still sits on the catalog default/free plan). The Organization
 * module provides the adapter; Billing never touches the organization or
 * plan aggregates directly.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationPlanPort
{
  // #region Methods
  /**
   * Method findCurrentPlan.
   *
   * Resolves the plan currently assigned to an organization (its explicit
   * assignment, falling back to the catalog default), mirroring the same
   * resolution rule the quota system and the compliance export entitlement
   * use.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?PlanSummary the plan summary, or null when it cannot be resolved
   */
  public function findCurrentPlan(string $organizationId): ?PlanSummary;
  // #endregion
}
