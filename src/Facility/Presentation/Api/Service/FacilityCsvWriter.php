<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Service;

use Facility\Application\Contract\Export\FacilityExportRow;
use Shared\Application\Port\Outbound\SpreadsheetSafeTextPort;

use function array_map;
use function fputcsv;

/**
 * Service FacilityCsvWriter.
 *
 * Writes a list of {@see FacilityExportRow} rows as CSV directly to a given
 * stream resource, one row at a time — mirrors
 * `Intervention\...\InterventionCsvWriter`. Every cell, including the
 * resolved `parentCode`, already exists on the row by the time it reaches
 * this writer: it formats, it never resolves anything itself.
 *
 * **The first seven header columns are the Import round-trip contract.**
 * {@see \Import\Application\Service\FacilityRowFactory} reads a CSV back
 * with exactly this column order (`type`, `name`, `code`, `address`,
 * `latitude`, `longitude`, `parentCode`) to provision facilities in bulk, so
 * a file exported here can be re-imported unchanged. Every column after
 * `parentCode` is read-only metadata the import side ignores. See
 * `tests/Unit/Facility/Presentation/Api/Service/FacilityCsvWriterTest.php`,
 * which freezes this ordering.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityCsvWriter
{
  // #region Constants
  /**
   * Constant HEADER.
   *
   * The first seven columns are the {@see \Import\Application\Service\FacilityRowFactory}
   * import contract, in the exact order that factory reads. Everything after
   * `parentCode` is read-only export metadata.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array HEADER = [
    'type',
    'name',
    'code',
    'address',
    'latitude',
    'longitude',
    'parentCode',
    'id',
    'status',
    'createdAt',
    'updatedAt',
    'levelIndex',
    SpreadsheetSafeTextPort::ENCODING_COLUMN,
  ];
  // #endregion

  // #region Constructor
  /**
   * Constructor
   *
   * @param SpreadsheetSafeTextPort $spreadsheetText reversible text-cell encoder
   */
  public function __construct(private SpreadsheetSafeTextPort $spreadsheetText)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Writes the CSV header followed by one row per facility.
   *
   * @since 1.0.0
   *
   * @param list<FacilityExportRow> $rows the facility export rows to write, in caller order
   * @param resource $handle an open, writable stream resource
   */
  public function write(array $rows, $handle): void
  {
    fputcsv($handle, self::HEADER, escape: '');

    foreach ($rows as $row) {
      $original = $this->toRow($row);
      $cells = array_map($this->spreadsheetText->encode(...), $original);
      // Coordinates and floor levels are typed numeric values, including negatives.
      foreach ([4, 5, 11] as $numericColumn) {
        $cells[$numericColumn] = $original[$numericColumn];
      }
      $cells[] = SpreadsheetSafeTextPort::ENCODING_VERSION;
      fputcsv($handle, $cells, escape: '');
    }
  }

  /**
   * Method toRow.
   *
   * Maps a single facility export row to a CSV row. Latitude/longitude are
   * formatted as plain decimal strings (no locale-dependent formatting) so
   * they round-trip through {@see \Import\Application\Service\FacilityRowFactory}'s
   * `is_numeric()` check unchanged.
   *
   * @since 1.0.0
   *
   * @param FacilityExportRow $row the facility export row
   *
   * @return list<string> the CSV row values
   */
  private function toRow(FacilityExportRow $row): array
  {
    return [
      $row->type,
      $row->name,
      $row->code ?? '',
      $row->address ?? '',
      null === $row->latitude ? '' : (string) $row->latitude,
      null === $row->longitude ? '' : (string) $row->longitude,
      $row->parentCode ?? '',
      $row->id,
      $row->status,
      $row->createdAt,
      $row->updatedAt,
      null === $row->levelIndex ? '' : (string) $row->levelIndex,
    ];
  }
  // #endregion
}
