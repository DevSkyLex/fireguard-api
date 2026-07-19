<?php

declare(strict_types=1);

namespace Automation\Application\Port\Outbound;

use Automation\Application\Contract\Policy\AutomationPolicy;

/**
 * Interface AutomationPolicyPort.
 *
 * Cross-module outbound port resolving an organization's effective
 * automation policy. Implemented by the Organization module (reads the
 * organization's own `OrganizationAutomationSettings` /
 * `OrganizationComplianceSettings`), mirroring
 * {@see \Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort}.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AutomationPolicyPort
{
  /**
   * Method policyFor.
   *
   * Resolves the automation policy for an organization. Implementations
   * must never throw on an unknown or malformed identifier: they fall back
   * to the permissive-off defaults (every rule disabled, catalog SLA
   * defaults) so a lookup failure can never silently trigger an automation
   * nor crash the caller.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return AutomationPolicy the effective automation policy
   */
  public function policyFor(string $organizationId): AutomationPolicy;
}
