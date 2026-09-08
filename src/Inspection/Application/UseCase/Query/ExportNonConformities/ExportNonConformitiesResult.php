<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\ExportNonConformities;

use Inspection\Application\Contract\Export\NonConformityExportRow;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportNonConformitiesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportNonConformitiesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<NonConformityExportRow> $rows the bounded, name-resolved export rows
   * @param int $total the total number of matching non-conformities
   */
  public function __construct(
    public array $rows,
    public int $total,
  ) {
  }
  // #endregion
}
