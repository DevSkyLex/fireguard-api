<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipments;

use Equipment\Application\Contract\Export\EquipmentExportRow;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportEquipmentsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportEquipmentsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<EquipmentExportRow> $rows the bounded, name-resolved export rows
   * @param int $total the total number of matching equipment items
   */
  public function __construct(
    public array $rows,
    public int $total,
  ) {
  }
  // #endregion
}
