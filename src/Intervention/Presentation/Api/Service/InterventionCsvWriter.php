<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Service;

use Intervention\Application\Contract\Export\InterventionExportRow;

use function fputcsv;

/**
 * Service InterventionCsvWriter.
 *
 * Writes a list of {@see InterventionExportRow} rows as CSV directly to a
 * given stream resource, one row at a time — mirrors
 * `Audit\...\AuditEventCsvWriter`. Every cell, including the resolved
 * `facility`/`assignee` display names, already exists on the row by the time
 * it reaches this writer: it formats, it never resolves anything itself.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionCsvWriter
{
  // #region Constants
  /**
   * Constant HEADER.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array HEADER = [
    'id',
    'name',
    'type',
    'status',
    'priority',
    'facility',
    'assignee',
    'due_at',
    'created_at',
    'updated_at',
  ];
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Writes the CSV header followed by one row per intervention.
   *
   * @since 1.0.0
   *
   * @param list<InterventionExportRow> $rows the intervention export rows to write, in caller order
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
   * Maps a single intervention export row to a CSV row. The `facility` and
   * `assignee` cells fall back to the raw identifier when the name could not
   * be resolved (a deleted facility, a member who left the organization), and
   * to an empty string only when the intervention carries no site/responsible
   * at all — a name that could not be resolved is not the same as an absent
   * one.
   *
   * @since 1.0.0
   *
   * @param InterventionExportRow $row the intervention export row
   *
   * @return list<string> the CSV row values
   */
  private function toRow(InterventionExportRow $row): array
  {
    return [
      $row->id,
      $row->name,
      $row->type,
      $row->status,
      $row->priority,
      $row->siteName ?? $row->siteId ?? '',
      $row->responsibleName ?? $row->responsibleId ?? '',
      $row->dueAt ?? '',
      $row->createdAt,
      $row->updatedAt,
    ];
  }
  // #endregion
}
