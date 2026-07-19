<?php

declare(strict_types=1);

namespace Import\Infrastructure\Csv;

use Generator;
use Import\Application\Port\Outbound\CsvRowStreamerPort;
use InvalidArgumentException;
use RuntimeException;

use function array_map;
use function fclose;
use function fgetcsv;
use function fopen;
use function fwrite;
use function implode;
use function rewind;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use function substr_count;
use function trim;

/**
 * Service CsvRowStreamer.
 *
 * Parses an uploaded CSV file's contents into an associative-array stream,
 * keyed by header column name, without ever materializing every row in
 * memory at once: `fgetcsv()` reads one line at a time from a `php://temp`
 * handle. The file's contents are already fully loaded as a PHP string by
 * the caller (bounded by the upload size policy), so bounding memory here is
 * specifically about the row-processing loop, not the blob itself.
 *
 * Handles a UTF-8 BOM, sniffs the delimiter (comma or semicolon — the
 * French-Excel convention) from the header line, and enforces a maximum
 * row count so a single import cannot run unbounded.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CsvRowStreamer implements CsvRowStreamerPort
{
  // #region Constants
  /**
   * Constant DEFAULT_MAX_ROWS.
   *
   * The default maximum number of data rows (header excluded) accepted in a
   * single import, bounding worker time and memory.
   *
   * @since 1.0.0
   *
   * @var int DEFAULT_MAX_ROWS
   */
  public const int DEFAULT_MAX_ROWS = 5000;

  /**
   * Constant BOM.
   *
   * @since 1.0.0
   *
   * @var string BOM
   */
  private const string BOM = "\xEF\xBB\xBF";
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $maxRows the maximum number of data rows accepted
   */
  public function __construct(
    private int $maxRows = self::DEFAULT_MAX_ROWS,
  ) {
  }
  // #endregion

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
   * @return int the data row count
   */
  public function countDataRows(string $contents): int
  {
    $count = 0;
    foreach ($this->rows($contents) as $ignored) {
      ++$count;
    }

    return $count;
  }

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
  public function rows(string $contents): Generator
  {
    $stream = $this->openStream($contents);

    try {
      $delimiter = $this->detectDelimiter($stream);

      $header = fgetcsv($stream, 0, $delimiter);
      if (false === $header || [null] === $header) {
        throw new InvalidArgumentException('The CSV file has no header row.');
      }

      /** @var list<string> $header */
      $header = array_map(static fn (?string $column): string => trim($column ?? ''), $header);

      $rowNumber = 0;
      while (false !== ($row = fgetcsv($stream, 0, $delimiter))) {
        if ([null] === $row) {
          // A fully blank line: fgetcsv() reports it as [null].
          continue;
        }

        ++$rowNumber;
        if ($rowNumber > $this->maxRows) {
          throw new InvalidArgumentException(sprintf(
            'The CSV file exceeds the maximum of %d data rows.',
            $this->maxRows,
          ));
        }

        yield $rowNumber => $this->combine($header, $row);
      }
    } finally {
      fclose($stream);
    }
  }

  /**
   * Method openStream.
   *
   * @since 1.0.0
   *
   * @param string $contents the raw CSV file contents
   *
   * @return resource the opened, BOM-stripped, rewound stream
   */
  private function openStream(string $contents)
  {
    if ('' === trim($contents)) {
      throw new InvalidArgumentException('The CSV file is empty.');
    }

    $body = str_starts_with($contents, self::BOM) ? substr($contents, 3) : $contents;

    $stream = fopen('php://temp', 'r+b');
    if (false === $stream) {
      throw new RuntimeException('Unable to open an in-memory stream to parse the CSV file.');
    }

    fwrite($stream, $body);
    rewind($stream);

    return $stream;
  }

  /**
   * Method detectDelimiter.
   *
   * Sniffs the header line for a semicolon (the common French-Excel export
   * convention) versus a comma, then rewinds the stream. Defaults to comma
   * when neither is more frequent.
   *
   * @since 1.0.0
   *
   * @param resource $stream the opened stream, positioned at the start
   *
   * @return string the detected delimiter
   */
  private function detectDelimiter($stream): string
  {
    $firstLine = fgetcsv($stream, 0, ',');
    rewind($stream);

    if (false === $firstLine) {
      return ',';
    }

    $rawFirstLine = implode(',', array_map(static fn (?string $value): string => $value ?? '', $firstLine));
    $semicolons = substr_count($rawFirstLine, ';');

    return $semicolons > 0 && !str_contains($rawFirstLine, ',') ? ';' : ',';
  }

  /**
   * Method combine.
   *
   * Combines a header row with a data row into an associative map, padding
   * short rows with empty strings and ignoring extra trailing columns.
   *
   * @since 1.0.0
   *
   * @param list<string> $header the trimmed header columns
   * @param list<?string> $row the raw data row
   *
   * @return array<string, string> the combined associative row
   */
  private function combine(array $header, array $row): array
  {
    $values = [];
    foreach ($header as $index => $column) {
      if ('' === $column) {
        continue;
      }

      $values[$column] = trim($row[$index] ?? '');
    }

    return $values;
  }
  // #endregion
}
