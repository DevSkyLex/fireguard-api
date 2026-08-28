<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipmentLabels;

use Equipment\Application\Contract\Export\EquipmentExportRow;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ExportEquipmentLabelsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportEquipmentLabelsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<EquipmentExportRow> $rows the name-resolved label rows
   * @param int $total the number of labels on the sheet
   * @param string $selection the selection mode (`ids`, `facility` or `organization`)
   */
  public function __construct(
    public array $rows,
    public int $total,
    public string $selection,
  ) {
  }
  // #endregion
}
