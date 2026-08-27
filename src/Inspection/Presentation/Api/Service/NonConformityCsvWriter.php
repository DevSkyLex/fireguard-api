<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Service;

use Inspection\Application\Contract\Export\NonConformityExportRow;

use function fputcsv;

/**
 * Service NonConformityCsvWriter.
 *
 * Writes a list of {@see NonConformityExportRow} rows as CSV directly to a
 * given stream resource, one row at a time — mirrors
 * `Intervention\...\InterventionCsvWriter` and
 * `Inspection\...\InspectionCsvWriter`. Every cell, including the resolved
 * `facility`/`equipment` display names and the precomputed `age_in_days`,
 * already exists on the row by the time it reaches this writer: it formats,
 * it never resolves or computes anything itself.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityCsvWriter
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
    'severity',
    'status',
    'age_in_days',
    'facility',
    'equipment',
    'inspection_id',
    'created_at',
    'resolved_at',
  ];
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Writes the CSV header followed by one row per non-conformity.
   *
   * @since 1.0.0
   *
   * @param list<NonConformityExportRow> $rows the non-conformity export rows to write, in caller order
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
   * Maps a single non-conformity export row to a CSV row. The `facility`
   * and `equipment` cells fall back to the raw identifier when the name
   * could not be resolved, and to an empty string only when the
   * non-conformity's inspection carries no such reference at all.
   *
   * @since 1.0.0
   *
   * @param NonConformityExportRow $row the non-conformity export row
   *
   * @return list<string> the CSV row values
   */
  private function toRow(NonConformityExportRow $row): array
  {
    return [
      $row->id,
      $row->severity,
      $row->status,
      (string) $row->ageInDays,
      $row->facilityName ?? $row->facilityId ?? '',
      $row->equipmentSerialNumber ?? $row->equipmentId ?? '',
      $row->inspectionId,
      $row->createdAt,
      $row->resolvedAt ?? '',
    ];
  }
  // #endregion
}
