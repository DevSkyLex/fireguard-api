<?php

declare(strict_types=1);

namespace Compliance\Application\Port\Outbound;

/**
 * Port InspectionComplianceStatisticsPort.
 *
 * Reads open non-conformity counts by severity, grouped by facility.
 * Implemented by an adapter hosted in the Inspection module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InspectionComplianceStatisticsPort
{
  // #region Methods
  /**
   * Method openNonConformitiesBySeverityByFacility.
   *
   * Counts open (`open`/`in_progress`) non-conformities grouped by facility
   * and severity. Facility attribution follows the parent inspection's
   * `facilityId`; inspections with no assigned facility bucket under the
   * `unassigned` key.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, array{low: int, medium: int, high: int, critical: int}> map of facilityId (or `unassigned`) => severity counts
   */
  public function openNonConformitiesBySeverityByFacility(string $organizationId): array;
  // #endregion
}
