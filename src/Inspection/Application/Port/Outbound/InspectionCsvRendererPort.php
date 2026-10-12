<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Application\Contract\Export\InspectionExportRow;

/** Formats authorized bounded rows into their original CSV bytes. @category Port */
interface InspectionCsvRendererPort
{
  /**
   * @param list<InspectionExportRow> $rows authorized rows
   */
  public function render(array $rows): string;
}
