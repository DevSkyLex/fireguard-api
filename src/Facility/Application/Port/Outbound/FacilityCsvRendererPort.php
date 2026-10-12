<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Application\Contract\Export\FacilityExportRow;

/** Encodes the original owner rows before their audit and receipt commit. @category Port */
interface FacilityCsvRendererPort
{
  /**
   * @param list<FacilityExportRow> $rows
   */
  public function render(array $rows): string;
}
