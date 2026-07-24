<?php

declare(strict_types=1);

namespace Compliance\Application\Port\Outbound;

/**
 * Port MaintenanceComplianceStatisticsPort.
 *
 * Reads the CURRENT maintenance-schedule due status per facility — never
 * recomputes periodicity, which is Maintenance's own recurring-sweep policy.
 * Implemented by an adapter hosted in the Maintenance module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceComplianceStatisticsPort
{
  // #region Methods
  /**
   * Method dueStatusCountsByFacility.
   *
   * Counts maintenance schedules grouped by facility and due status.
   * Facilities with no schedule at all are absent from the map; equipment
   * with no assigned facility is bucketed under the `unassigned` key.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, array{up_to_date: int, due_soon: int, overdue: int, unscheduled: int}> map of facilityId (or `unassigned`) => due status counts
   */
  public function dueStatusCountsByFacility(string $organizationId): array;

  /**
   * Method lastInspectionClosedAtByFacility.
   *
   * Returns the most recent `lastInspectionClosedAt` among a facility's
   * maintenance schedules, refreshed by the Inspection→Maintenance
   * synchronizer whenever an inspection closes. Facilities with no closed
   * inspection yet are absent from the map.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, string> map of facilityId (or `unassigned`) => ISO 8601 datetime
   */
  public function lastInspectionClosedAtByFacility(string $organizationId): array;
  // #endregion
}
