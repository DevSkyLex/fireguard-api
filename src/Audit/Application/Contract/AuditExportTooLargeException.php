<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

use RuntimeException;

use function sprintf;

/**
 * Exception AuditExportTooLargeException.
 *
 * Thrown when a CSV export request matches more audit events than the
 * export's row cap ({@see \Audit\Application\UseCase\Query\ExportAuditEvents\ExportAuditEventsHandler::MAX_EXPORT_ROWS}).
 * The ledger stream() itself never materializes the full result set in
 * memory, but an unbounded export is still unbounded work — this cap is
 * checked with a cheap COUNT before streaming a single row, so the caller
 * fails fast with actionable guidance instead of downloading a
 * multi-hour CSV.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditExportTooLargeException extends RuntimeException
{
  // #region Methods
  /**
   * Method exceedsCap.
   *
   * @since 1.0.0
   *
   * @param int $matched the number of audit events matched by the filters
   * @param int $maxRows the export row cap
   *
   * @return self the exception instance
   */
  public static function exceedsCap(int $matched, int $maxRows): self
  {
    return new self(sprintf(
      'The export matches %d audit events, exceeding the %d row export cap. Narrow the filters (for example a shorter "from"/"to" date range, or a specific actorId/subjectId) and try again.',
      $matched,
      $maxRows,
    ));
  }
  // #endregion
}
