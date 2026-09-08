<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\ExportInspections;

use Inspection\Application\Contract\Export\InspectionExportRow;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportInspectionsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportInspectionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<InspectionExportRow> $rows the bounded, name-resolved export rows
   * @param int $total the total number of matching inspections
   */
  public function __construct(
    public array $rows,
    public int $total,
  ) {
  }
  // #endregion
}
