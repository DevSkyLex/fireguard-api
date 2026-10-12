<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Application\Contract\Export\NonConformityExportRow;

/** Formats authorized bounded rows into their original CSV bytes. @category Port */
interface NonConformityCsvRendererPort
{
  /**
   * @param list<NonConformityExportRow> $rows authorized rows
   */
  public function render(array $rows): string;
}
