<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Service;

use Equipment\Application\Contract\Export\EquipmentExportRow;

use function fputcsv;

/**
 * Service EquipmentCsvWriter.
 *
 * Writes a list of {@see EquipmentExportRow} rows as CSV directly to a given
 * stream resource, one row at a time — mirrors
 * `Intervention\Presentation\Api\Service\InterventionCsvWriter`. Every cell,
 * including the resolved `facility` display name, already exists on the row
 * by the time it reaches this writer: it formats, it never resolves anything
 * itself.
 *
 * {@see self::HEADER}'s first six columns (`type`, `subType`, `brand`,
 * `model`, `serialNumber`, `locationLabel`) are a published, load-bearing
 * contract: they are, in that exact order, the columns
 * `Import\Application\Service\EquipmentRowFactory` reads back on reimport.
 * Everything after column 6 is read-only metadata the import ignores. Do not
 * reorder the first six without checking `EquipmentRowFactory`'s docblock —
 * and the frozen slice assertion in
 * `tests/Unit/Equipment/Presentation/Api/Service/EquipmentCsvWriterTest.php`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentCsvWriter
{
  // #region Constants
  /**
   * Constant HEADER.
   *
   * Public on purpose: it is the frozen import round-trip contract, and the
   * test that locks it down (`array_slice(self::HEADER, 0, 6)`) must be able
   * to read it from outside the class.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array HEADER = [
    // The import round-trip contract — see class docblock. Never reorder.
    'type',
    'subType',
    'brand',
    'model',
    'serialNumber',
    'locationLabel',
    // Read-only metadata, ignored by the importer.
    'id',
    'status',
    'facilityId',
    'facilityName',
    'installedAt',
    'commissionedAt',
    'createdAt',
    'updatedAt',
  ];
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Writes the CSV header followed by one row per equipment item.
   *
   * @since 1.0.0
   *
   * @param list<EquipmentExportRow> $rows the equipment export rows to write, in caller order
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
   * Maps a single equipment export row to a CSV row, in {@see self::HEADER}
   * order. The `facilityName` cell falls back to the raw identifier when the
   * name could not be resolved (a deleted facility), and to an empty string
   * only when the equipment carries no facility at all — a name that could
   * not be resolved is not the same as an absent one.
   *
   * @since 1.0.0
   *
   * @param EquipmentExportRow $row the equipment export row
   *
   * @return list<string> the CSV row values
   */
  private function toRow(EquipmentExportRow $row): array
  {
    return [
      $row->type,
      $row->subType ?? '',
      $row->brand ?? '',
      $row->model ?? '',
      $row->serialNumber ?? '',
      $row->locationLabel ?? '',
      $row->id,
      $row->status,
      $row->facilityId ?? '',
      $row->facilityName ?? $row->facilityId ?? '',
      $row->installedAt ?? '',
      $row->commissionedAt ?? '',
      $row->createdAt,
      $row->updatedAt,
    ];
  }
  // #endregion
}
