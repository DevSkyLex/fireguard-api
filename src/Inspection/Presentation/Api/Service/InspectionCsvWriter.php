<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Service;

use Inspection\Application\Contract\Export\InspectionExportRow;

use function fputcsv;

/**
 * Service InspectionCsvWriter.
 *
 * Writes a list of {@see InspectionExportRow} rows as CSV directly to a
 * given stream resource, one row at a time — mirrors
 * `Intervention\...\InterventionCsvWriter`. Every cell, including the
 * resolved `facility`/`equipment`/`checklist` display names, already exists
 * on the row by the time it reaches this writer: it formats, it never
 * resolves anything itself.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionCsvWriter
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
    'status',
    'result',
    'facility',
    'equipment',
    'checklist',
    'performed_at',
    'non_conformities_open',
    'non_conformities_total',
    'created_at',
    'updated_at',
  ];
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Writes the CSV header followed by one row per inspection.
   *
   * @since 1.0.0
   *
   * @param list<InspectionExportRow> $rows the inspection export rows to write, in caller order
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
   * Maps a single inspection export row to a CSV row. The `facility`,
   * `equipment`, and `checklist` cells fall back to the raw identifier when
   * the name could not be resolved, and to an empty string only when the
   * inspection carries no such reference at all — a name that could not be
   * resolved is not the same as an absent one.
   *
   * @since 1.0.0
   *
   * @param InspectionExportRow $row the inspection export row
   *
   * @return list<string> the CSV row values
   */
  private function toRow(InspectionExportRow $row): array
  {
    return [
      $row->id,
      $row->status,
      $row->result,
      $row->facilityName ?? $row->facilityId ?? '',
      $row->equipmentSerialNumber ?? $row->equipmentId,
      $row->checklistName ?? $row->checklistId ?? '',
      $row->performedAt,
      (string) $row->nonConformitiesOpen,
      (string) $row->nonConformitiesTotal,
      $row->createdAt,
      $row->updatedAt,
    ];
  }
  // #endregion
}
