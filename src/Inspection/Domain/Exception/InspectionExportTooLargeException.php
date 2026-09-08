<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception InspectionExportTooLargeException.
 *
 * Raised by both the inspection and the non-conformity CSV exports when the
 * filtered result exceeds the row cap — mirrors
 * `Intervention\...\InterventionExportTooLargeException`. Shared across the
 * two export use cases rather than duplicated: both belong to this module and
 * the message is generic enough ("the export") to cover either.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionExportTooLargeException extends RuntimeException
{
  // #region Methods
  /**
   * Method exceedsCap.
   *
   * @since 1.0.0
   *
   * @param int $matched the number of rows matched by the filters
   * @param int $maxRows the export row cap
   *
   * @return self the exception instance
   */
  public static function exceedsCap(int $matched, int $maxRows): self
  {
    return new self(sprintf(
      'The export matches %d rows, exceeding the %d row export cap. Narrow the filters and try again.',
      $matched,
      $maxRows,
    ));
  }
  // #endregion
}
