<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Application\Contract\Export\EquipmentExportRow;

/** Encodes the original owner rows before their audit and receipt commit. @category Port */
interface EquipmentCsvRendererPort
{
  /**
   * @param list<EquipmentExportRow> $rows
   */
  public function render(array $rows): string;
}
