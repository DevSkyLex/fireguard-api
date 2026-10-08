<?php

declare(strict_types=1);

namespace MaintenanceExport\Domain\ValueObject;

use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use Shared\Domain\ValueObject\DecimalAmount;

use function array_keys;
use function array_unique;
use function count;
use function is_int;
use function is_string;
use function sort;

/**
 * Class ExportRows
 * Source changes become explicit reversals and additions, preserving original row identity.
 *
 * @category ValueObject
 */
final readonly class ExportRows
{
  // #region Methods
  /**
   * Method initial
   *
   * @param array<string,array<string,mixed>> $baseline frozen source rows
   *
   * @return list<array<string,mixed>> stable ordered export rows
   */
  public static function initial(array $baseline): array
  {
    $keys = array_keys($baseline);
    sort($keys);
    $rows = [];
    foreach ($keys as $key) {
      $rows[] = [...$baseline[$key], 'change' => 'add', 'correctionOf' => null];
    }
    self::assertBounded($rows);

    return $rows;
  }

  /**
   * Method adjustment
   *
   * @param array<string,array<string,mixed>> $before preceding captured facts
   * @param array<string,array<string,mixed>> $after new facts
   * @param string $adjustmentId stable new artifact identity
   *
   * @return list<array<string,mixed>> explicit compensation and replacement
   */
  public static function adjustment(array $before, array $after, string $adjustmentId): array
  {
    $keys = array_unique([...array_keys($before), ...array_keys($after)]);
    sort($keys);
    $rows = [];
    foreach ($keys as $key) {
      $old = $before[$key] ?? null;
      $new = $after[$key] ?? null;
      if (null !== $old && null !== $new && self::sameFacts($old, $new)) {
        continue;
      }
      if (null !== $old) {
        $rows[] = self::reversal($old, $adjustmentId, $key);
      }
      if (null !== $new) {
        $rows[] = [...$new, 'id' => ExportArtifact::rowId($adjustmentId . '|add|' . $key), 'change' => 'add', 'correctionOf' => $old['id'] ?? null];
      }
    }
    if ([] === $rows) {
      throw MaintenanceExportException::conflict('No validated source correction is available for adjustment.');
    }
    self::assertBounded($rows);

    return $rows;
  }

  /**
   * Method sameFacts
   *
   * Artifact row identity and PostgreSQL object ordering cannot invent a correction.
   *
   * @access private
   *
   * @param array<string,mixed> $before preceding retained fact
   * @param array<string,mixed> $after current source fact
   *
   * @return bool canonical facts are unchanged
   */
  private static function sameFacts(array $before, array $after): bool
  {
    unset($before['id'], $after['id']);

    return ExportArtifact::json($before) === ExportArtifact::json($after);
  }

  /**
   * Method reversal
   *
   * Unknown amounts remain unknown; known quantities are compensated exactly.
   *
   * @access private
   *
   * @param array<string,mixed> $row preceding retained row
   * @param string $adjustmentId identity of the compensating artifact
   * @param string $key stable logical source key
   *
   * @return array<string,mixed> linked compensating row
   */
  private static function reversal(array $row, string $adjustmentId, string $key): array
  {
    $reversal = [...$row, 'id' => ExportArtifact::rowId($adjustmentId . '|reverse|' . $key), 'change' => 'reverse', 'correctionOf' => $row['id']];
    if (is_int($row['minutes'] ?? null)) {
      $reversal['minutes'] = -$row['minutes'];
    }
    if (is_string($row['amount'] ?? null)) {
      $reversal['amount'] = DecimalAmount::zero()->subtract(DecimalAmount::fromString($row['amount']))->toString();
    }

    return $reversal;
  }

  /**
   * Method assertBounded
   *
   * @param list<array<string,mixed>> $rows synchronous row work
   *
   * @return void
   */
  private static function assertBounded(array $rows): void
  {
    if ([] === $rows || count($rows) > ExportArtifact::MAX_ROWS) {
      throw MaintenanceExportException::invalid('An export requires between one and 10000 rows.');
    }
  }
  // #endregion
}
