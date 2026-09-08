<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Service;

use Maintenance\Application\Contract\Export\MaintenanceScheduleExportRow;

use function fputcsv;

/**
 * Service MaintenanceScheduleCsvWriter.
 *
 * Writes a list of {@see MaintenanceScheduleExportRow} rows as CSV directly
 * to a given stream resource, one row at a time — mirrors
 * `Intervention\...\InterventionCsvWriter`. Every cell, including the
 * resolved `equipment_serial`/`facility` display values, already exists on
 * the row by the time it reaches this writer: it formats, it never resolves
 * anything itself.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceScheduleCsvWriter
{
  // #region Constants
  /**
   * Constant HEADER.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array HEADER = [
    'id',
    'equipment_id',
    'equipment_type',
    'equipment_serial',
    'facility',
    'periodicity_override',
    'last_inspection_closed_at',
    'next_due_at',
    'due_status',
    'created_at',
    'updated_at',
  ];
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Writes the CSV header followed by one row per maintenance schedule.
   *
   * @since 1.0.0
   *
   * @param list<MaintenanceScheduleExportRow> $rows the maintenance schedule export rows to write, in caller order
   * @param resource $handle an open, writable stream resource
   */
  public function write(array $rows, $handle): void
  {
    fputcsv($handle, self::HEADER, escape: '\\');

    foreach ($rows as $row) {
      fputcsv($handle, $this->toRow($row), escape: '\\');
    }
  }

  /**
   * Method toRow.
   *
   * Maps a single maintenance schedule export row to a CSV row. The
   * `facility` cell falls back to the raw identifier when the name could not
   * be resolved (a deleted facility), and to an empty string only when the
   * schedule carries no facility at all — a name that could not be resolved
   * is not the same as an absent one. `equipment_serial` is left empty when
   * unresolved, since the equipment identifier itself is already carried by
   * `equipment_id`.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleExportRow $row the maintenance schedule export row
   *
   * @return list<string> the CSV row values
   */
  private function toRow(MaintenanceScheduleExportRow $row): array
  {
    return [
      $row->id,
      $row->equipmentId,
      $row->equipmentType,
      $row->equipmentSerial ?? '',
      $row->facilityName ?? $row->facilityId ?? '',
      $row->intervalOverride ?? '',
      $row->lastInspectionClosedAt ?? '',
      $row->nextDueAt ?? '',
      $row->dueStatus,
      $row->createdAt,
      $row->updatedAt,
    ];
  }
  // #endregion
}
