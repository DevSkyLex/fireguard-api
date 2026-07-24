<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Compliance;

use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;

/**
 * Port MaintenanceCompliancePolicyPort.
 *
 * Cross-module read access to an organization's compliance policy as it
 * pertains to maintenance scheduling. Implemented by an Organization-module
 * adapter
 * (`Organization\Infrastructure\Adapter\Maintenance\OrganizationCompliancePolicyAdapter`),
 * mirroring `OrganizationNotificationPolicyPort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceCompliancePolicyPort
{
  // #region Methods
  /**
   * Method compliancePolicy.
   *
   * Resolves the compliance policy for an organization. Implementations
   * must never throw on an unknown or malformed identifier: they fall back
   * to the catalog defaults (no periodicities customized) so a lookup
   * failure can never crash the recompute path.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return MaintenanceCompliancePolicy the effective compliance policy
   */
  public function compliancePolicy(string $organizationId): MaintenanceCompliancePolicy;
  // #endregion
}
