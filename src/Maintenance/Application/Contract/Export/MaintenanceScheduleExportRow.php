<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Export;

/**
 * Domain MaintenanceScheduleExportRow.
 *
 * One maintenance schedule row ready for the CSV export, carrying the
 * equipment serial number and the facility display name already resolved in
 * bulk by {@see \Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\ExportMaintenanceSchedulesHandler}
 * through {@see \Maintenance\Application\Port\Outbound\Naming\MaintenanceEquipmentNamingPort}
 * and {@see \Maintenance\Application\Port\Outbound\Naming\MaintenanceFacilityNamingPort} —
 * the Presentation-layer CSV writer only formats, it never resolves a name
 * itself. Deliberately a type distinct from {@see MaintenanceScheduleExportCandidate},
 * per the module's naming convention that contract types stay distinct from
 * use case Results and from each other's intermediate shapes.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleExportRow
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the maintenance schedule identifier
   * @param string $equipmentId the tracked equipment identifier
   * @param string $equipmentType the denormalized equipment type value
   * @param ?string $equipmentSerial the resolved equipment serial number, when resolvable
   * @param ?string $facilityId the denormalized facility identifier, if any
   * @param ?string $facilityName the resolved facility display name, when the facility is set and resolvable
   * @param ?string $intervalOverride the per-schedule ISO-8601 periodicity override, if any
   * @param ?string $lastInspectionClosedAt the last inspection closure date, ISO 8601, if any
   * @param ?string $nextDueAt the next due date, ISO 8601, if any
   * @param string $dueStatus the due status value
   * @param string $createdAt the creation date, ISO 8601
   * @param string $updatedAt the last update date, ISO 8601
   */
  public function __construct(
    public string $id,
    public string $equipmentId,
    public string $equipmentType,
    public ?string $equipmentSerial,
    public ?string $facilityId,
    public ?string $facilityName,
    public ?string $intervalOverride,
    public ?string $lastInspectionClosedAt,
    public ?string $nextDueAt,
    public string $dueStatus,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
