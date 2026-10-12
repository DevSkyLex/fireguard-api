<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Export;

use Maintenance\Application\Contract\Export\MaintenanceScheduleExportRow;

/**
 * Interface MaintenanceScheduleCsvRendererPort
 *
 * Renders the authorized immutable rows before the export transaction commits.
 *
 * @category Port
 */
interface MaintenanceScheduleCsvRendererPort
{
  /**
   * @param list<MaintenanceScheduleExportRow> $rows rows in original owner order
   *
   * @return string original CSV bytes, including header and escaping
   */
  public function render(array $rows): string;
}
