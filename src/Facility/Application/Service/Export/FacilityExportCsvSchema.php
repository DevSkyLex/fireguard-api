<?php

declare(strict_types=1);

namespace Facility\Application\Service\Export;

use Facility\Application\Contract\Export\FacilityExportRow;
use Shared\Application\Port\Outbound\SpreadsheetSafeTextPort;

use function array_map;
use function array_values;

/**
 * Service FacilityExportCsvSchema.
 *
 * Maps immutable export rows to the exact spreadsheet-safe CSV cells used by
 * both the streamed writer and durable artifact renderer. Parent codes and all
 * other display values are resolved by the owning query beforehand.
 *
 * **The first seven header columns are the Import round-trip contract.**
 * the Import module CSV reader reads a CSV back
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
final readonly class FacilityExportCsvSchema
{
  // #region Constants
  /**
   * Constant HEADER.
   *
   * The first seven columns are the the Import module CSV reader
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
   * Method cells
   *
   * @param FacilityExportRow $row original owner export values
   *
   * @return list<string> exact import-compatible, spreadsheet-safe cells
   */
  public function cells(FacilityExportRow $row): array
  {
    $original = $this->toRow($row);
    $cells = array_map($this->spreadsheetText->encode(...), $original);
    foreach ([4, 5, 11] as $numericColumn) {
      $cells[$numericColumn] = $original[$numericColumn];
    }
    $cells[] = SpreadsheetSafeTextPort::ENCODING_VERSION;

    return array_values($cells);
  }

  /**
   * Method toRow.
   *
   * Maps a single facility export row to a CSV row. Latitude/longitude are
   * formatted as plain decimal strings (no locale-dependent formatting) so
   * they round-trip through the Import module CSV reader's
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
