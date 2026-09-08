<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound\Compliance;

use Inspection\Application\Contract\Sla\NonConformitySlaPolicy;

/**
 * Port NonConformitySlaPolicyPort.
 *
 * Cross-module read access to an organization's non-conformity resolution
 * SLAs. Implemented by an Organization-module adapter
 * (`Organization\Infrastructure\Adapter\Inspection\OrganizationNonConformitySlaPolicyAdapter`),
 * mirroring `Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NonConformitySlaPolicyPort
{
  // #region Methods
  /**
   * Method slaPolicy.
   *
   * Resolves the effective non-conformity SLA policy for an organization.
   * Implementations must never throw on an unknown or malformed identifier:
   * they fall back to the catalog defaults so one bad lookup can never crash
   * the escalation sweep.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return NonConformitySlaPolicy the effective SLA policy
   */
  public function slaPolicy(string $organizationId): NonConformitySlaPolicy;
  // #endregion
}
