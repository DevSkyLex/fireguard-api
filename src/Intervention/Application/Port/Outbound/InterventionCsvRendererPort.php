<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Export\InterventionExportRow;

/** Formats authorized bounded rows into their original CSV bytes. @category Port */
interface InterventionCsvRendererPort
{
  /**
   * @param list<InterventionExportRow> $rows authorized rows
   */
  public function render(array $rows): string;
}
