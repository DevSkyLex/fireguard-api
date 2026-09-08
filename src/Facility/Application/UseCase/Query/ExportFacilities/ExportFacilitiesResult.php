<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\ExportFacilities;

use Facility\Application\Contract\Export\FacilityExportRow;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportFacilitiesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportFacilitiesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<FacilityExportRow> $rows the bounded, code-resolved export rows
   * @param int $total the total number of matching facilities
   */
  public function __construct(
    public array $rows,
    public int $total,
  ) {
  }
  // #endregion
}
