<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Export;

/**
 * Domain MaintenanceScheduleExportCandidate.
 *
 * A single maintenance schedule row as read from
 * {@see \Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort::listExportCandidates()},
 * carrying only the raw `equipmentId`/`facilityId` identifiers — no display
 * name is resolved yet, mirroring `Intervention\...\InterventionExportCandidate`.
 * {@see \Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\ExportMaintenanceSchedulesHandler}
 * turns a batch of these into {@see MaintenanceScheduleExportRow} once the
 * equipment serial number and the facility name have been resolved in bulk.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleExportCandidate
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
   * @param ?string $facilityId the denormalized facility identifier, if any
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
    public ?string $facilityId,
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
