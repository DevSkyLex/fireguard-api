<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception EquipmentExportTooLargeException.
 *
 * Thrown when a CSV export request matches more equipment than the export's
 * row cap ({@see \Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsHandler::MAX_EXPORT_ROWS}).
 * Checked with a cheap COUNT before any row is fetched, so the caller fails
 * fast with actionable guidance instead of downloading a multi-hour CSV —
 * mirrors `Intervention\Domain\Exception\InterventionExportTooLargeException`.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentExportTooLargeException extends RuntimeException
{
  // #region Methods
  /**
   * Method exceedsCap.
   *
   * @since 1.0.0
   *
   * @param int $matched the number of equipment items matched by the scope
   * @param int $maxRows the export row cap
   *
   * @return self the exception instance
   */
  public static function exceedsCap(int $matched, int $maxRows): self
  {
    return new self(sprintf(
      'The export matches %d equipment items, exceeding the %d row export cap. Narrow the scope (for example export one facility at a time) and try again.',
      $matched,
      $maxRows,
    ));
  }
  // #endregion
}
