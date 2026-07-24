<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound;

use Generator;
use InvalidArgumentException;

/**
 * Port CsvRowStreamerPort.
 *
 * Outbound port hiding the concrete CSV parsing adapter
 * ({@see \Import\Infrastructure\Csv\CsvRowStreamer}) from
 * `ProcessImportJobHandler`, so the Application layer never depends on
 * Infrastructure directly.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CsvRowStreamerPort
{
  // #region Methods
  /**
   * Method countDataRows.
   *
   * Counts the data rows (header excluded) without keeping them in memory.
   *
   * @since 1.0.0
   *
   * @param string $contents the raw CSV file contents
   *
   * @throws InvalidArgumentException when the file is empty, has no header
   *                                  row, or exceeds the maximum row count
   *
   * @return int the data row count
   */
  public function countDataRows(string $contents): int;

  /**
   * Method rows.
   *
   * Streams the CSV file's data rows, 1-based row number (header excluded)
   * as key, associative `column => value` map as value.
   *
   * @since 1.0.0
   *
   * @param string $contents the raw CSV file contents
   *
   * @throws InvalidArgumentException when the file is empty, has no header
   *                                  row, or exceeds the maximum row count
   *
   * @return Generator<int, array<string, string>> the streamed data rows
   */
  public function rows(string $contents): Generator;
  // #endregion
}
