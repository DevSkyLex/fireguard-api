<?php

declare(strict_types=1);

namespace Maintenance\Application\Contract\Export;

/**
 * Class MaintenanceScheduleCsvSchema
 *
 * Publishes the existing owner CSV field order and fallback values.
 *
 * @category Contract
 */
final class MaintenanceScheduleCsvSchema
{
  /**
   * @var list<string>
   */
  public const array HEADER = ['id', 'equipment_id', 'equipment_type', 'equipment_serial', 'facility', 'periodicity_override', 'last_inspection_closed_at', 'next_due_at', 'due_status', 'created_at', 'updated_at'];

  /**
   * @param MaintenanceScheduleExportRow $row authorized owner row
   *
   * @return list<string> cells in the existing public export order
   */
  public static function cells(MaintenanceScheduleExportRow $row): array
  {
    return [$row->id, $row->equipmentId, $row->equipmentType, $row->equipmentSerial ?? '', $row->facilityName ?? $row->facilityId ?? '', $row->intervalOverride ?? '', $row->lastInspectionClosedAt ?? '', $row->nextDueAt ?? '', $row->dueStatus, $row->createdAt, $row->updatedAt];
  }
}
