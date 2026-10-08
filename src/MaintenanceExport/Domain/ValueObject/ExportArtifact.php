<?php

declare(strict_types=1);

namespace MaintenanceExport\Domain\ValueObject;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;

use function array_is_list;
use function count;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function ksort;
use function preg_match;
use function str_replace;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Class ExportArtifact
 * Canonical exact JSON and formula-safe RFC4180 CSV are generated only once.
 *
 * @category ValueObject
 */
final readonly class ExportArtifact
{
  // #region Constants
  /**
   * Constant MAX_ROWS
   * Bounded synchronous work.
   */
  public const int MAX_ROWS = 10000;

  /**
   * Constant MAX_BYTES
   * Maximum bytes per retained artifact.
   */
  public const int MAX_BYTES = 10485760;
  // #endregion

  // #region Methods
  /**
   * Method json
   *
   * @param array<string,mixed> $data frozen document
   *
   * @return string canonical bytes
   */
  public static function json(array $data): string
  {
    $encoded = json_encode(self::canonical($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    if (strlen($encoded) > self::MAX_BYTES) {
      throw MaintenanceExportException::invalid('Export artifact exceeds the size limit.');
    }

    return $encoded;
  }

  /**
   * Method csv
   *
   * @param list<array<string,mixed>> $rows frozen rows
   * @param bool $financial include dedicated private columns
   *
   * @return string retained CSV bytes
   */
  public static function csv(array $rows, bool $financial): string
  {
    if (count($rows) > self::MAX_ROWS) {
      throw MaintenanceExportException::invalid('Too many export rows.');
    }
    $columns = ['id', 'kind', 'change', 'correctionOf', 'interventionId', 'publicationId', 'publicationRevision', 'workItemId', 'sourceId', 'sourceRevision', 'equipmentId', 'equipmentName', 'assetReference', 'siteId', 'siteName', 'customerId', 'customerName', 'equipmentReference', 'siteReference', 'customerReference', 'action', 'performedOn', 'outcome', 'minutes', 'evidenceCount'];
    if ($financial) {
      $columns = [...$columns, 'amount', 'currency', 'costComplete'];
    }
    $lines = [implode(',', $columns)];
    foreach ($rows as $row) {
      $cells = [];
      foreach ($columns as $column) {
        $cells[] = self::csvCell($row[$column] ?? null, $column);
      }
      $lines[] = implode(',', $cells);
    }
    $bytes = implode("\r\n", $lines) . "\r\n";
    if (strlen($bytes) > self::MAX_BYTES) {
      throw MaintenanceExportException::invalid('Export artifact exceeds the size limit.');
    }

    return $bytes;
  }

  /**
   * Method rowId
   *
   * @param string $key immutable source key
   *
   * @return string deterministic stable UUID
   */
  public static function rowId(string $key): string
  {
    $hex = hash('sha256', $key);

    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-5' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
  }

  /**
   * Method csvCell
   *
   * Preserves ASCII decimal columns while neutralizing formulas in textual values.
   *
   * @access private
   *
   * @param mixed $value retained column value
   * @param string $column ordered CSV column
   *
   * @return string formula-safe RFC4180 quoted cell
   */
  private static function csvCell(mixed $value, string $column): string
  {
    $cell = self::csvText($value);
    $numeric = in_array($column, ['minutes', 'amount', 'sourceRevision', 'publicationRevision', 'evidenceCount'], true) && 1 === preg_match('/^-?\d+(?:\.\d+)?$/', $cell);
    if (!$numeric && 1 === preg_match('/^[\x00-\x20]*[=+\-@]/', $cell)) {
      $cell = "'" . $cell;
    }

    return '"' . str_replace('"', '""', $cell) . '"';
  }

  /**
   * Method csvText
   *
   * @access private
   *
   * @param mixed $value retained scalar or explicit unknown
   *
   * @return string exact CSV scalar representation
   */
  private static function csvText(mixed $value): string
  {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    return is_string($value) || is_int($value) ? (string) $value : '';
  }

  /**
   * Method canonical
   *
   * @param mixed $value exact JSON value
   *
   * @return mixed sorted associative maps
   */
  private static function canonical(mixed $value): mixed
  {
    if (is_float($value)) {
      throw MaintenanceExportException::invalid('Export amounts must use exact decimal strings.');
    }
    if (is_array($value)) {
      if (!array_is_list($value)) {
        ksort($value);
      }
      foreach ($value as $key => $item) {
        $value[$key] = self::canonical($item);
      }
    }

    return $value;
  }
  // #endregion
}
