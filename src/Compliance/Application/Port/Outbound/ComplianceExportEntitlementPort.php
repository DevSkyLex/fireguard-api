<?php

declare(strict_types=1);

namespace Compliance\Application\Port\Outbound;

/**
 * Port ComplianceExportEntitlementPort.
 *
 * Resolves whether an organization's current plan tier entitles it to
 * export the regulatory safety register PDF (reserved to `pro`/`max`).
 * Implemented by an adapter hosted in the Organization module, reusing the
 * same plan-resolution logic as `OrganizationQuotaService::resolvePlan()`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ComplianceExportEntitlementPort
{
  // #region Methods
  /**
   * Method isExportEntitled.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return bool whether the organization's plan entitles it to export the safety register
   */
  public function isExportEntitled(string $organizationId): bool;

  /**
   * Method resolvePlanKey.
   *
   * Resolves the organization's current plan key, for the audit event
   * payload and the register's footer.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?string the plan key, or null when no plan could be resolved
   */
  public function resolvePlanKey(string $organizationId): ?string;
  // #endregion
}
