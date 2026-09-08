<?php

declare(strict_types=1);

namespace Maintenance\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception MaintenanceExportTooLargeException.
 *
 * Thrown when a CSV export request matches more maintenance schedules than
 * the export's row cap ({@see \Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\ExportMaintenanceSchedulesHandler::MAX_EXPORT_ROWS}).
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
final class MaintenanceExportTooLargeException extends RuntimeException
{
  // #region Methods
  /**
   * Method exceedsCap.
   *
   * @since 1.0.0
   *
   * @param int $matched the number of schedules matched by the filters
   * @param int $maxRows the export row cap
   *
   * @return self the exception instance
   */
  public static function exceedsCap(int $matched, int $maxRows): self
  {
    return new self(sprintf(
      'The export matches %d maintenance schedules, exceeding the %d row export cap. Narrow the filters (for example a specific facility or equipment type) and try again.',
      $matched,
      $maxRows,
    ));
  }
  // #endregion
}
