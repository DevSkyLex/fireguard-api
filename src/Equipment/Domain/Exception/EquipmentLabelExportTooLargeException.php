<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception EquipmentLabelExportTooLargeException.
 *
 * Thrown when a QR label sheet request matches more equipment than the
 * sheet's label cap ({@see \Equipment\Application\UseCase\Query\ExportEquipmentLabels\ExportEquipmentLabelsHandler::MAX_LABELS}).
 * Checked with a cheap COUNT before any row is fetched, so the caller fails
 * fast with actionable guidance instead of waiting on a 20+-page PDF render —
 * mirrors {@see EquipmentExportTooLargeException}.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentLabelExportTooLargeException extends RuntimeException
{
  // #region Methods
  /**
   * Method exceedsCap.
   *
   * @since 1.0.0
   *
   * @param int $matched the number of equipment items matched by the selection
   * @param int $maxLabels the label cap
   *
   * @return self the exception instance
   */
  public static function exceedsCap(int $matched, int $maxLabels): self
  {
    return new self(sprintf(
      'The label sheet request matches %d equipment items, exceeding the %d label cap. Narrow the selection (for example print one facility at a time) and try again.',
      $matched,
      $maxLabels,
    ));
  }
  // #endregion
}
