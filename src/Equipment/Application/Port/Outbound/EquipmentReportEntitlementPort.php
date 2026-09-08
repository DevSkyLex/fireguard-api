<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Port EquipmentReportEntitlementPort.
 *
 * Resolves whether an organization's current plan tier entitles it to
 * export the equipment sheet PDF (reserved to `pro`/`max`, the SAME gate as
 * the Compliance safety register — a deliberate product decision aligning
 * every PDF document export on one entitlement). Implemented by the same
 * Organization-module adapter as
 * `Compliance\Application\Port\Outbound\ComplianceExportEntitlementPort`,
 * so the `pro`/`max` allow-list lives in exactly one place.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentReportEntitlementPort
{
  // #region Methods
  /**
   * Method isExportEntitled.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return bool whether the organization's plan entitles it to export the equipment sheet PDF
   */
  public function isExportEntitled(string $organizationId): bool;

  /**
   * Method resolvePlanKey.
   *
   * Resolves the organization's current plan key, for the audit event
   * payload.
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
