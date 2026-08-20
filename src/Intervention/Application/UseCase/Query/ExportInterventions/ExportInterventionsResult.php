<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\ExportInterventions;

use Intervention\Application\Contract\Export\InterventionExportRow;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportInterventionsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportInterventionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<InterventionExportRow> $rows the bounded, name-resolved export rows
   * @param int $total the total number of matching interventions
   */
  public function __construct(
    public array $rows,
    public int $total,
  ) {
  }
  // #endregion
}
