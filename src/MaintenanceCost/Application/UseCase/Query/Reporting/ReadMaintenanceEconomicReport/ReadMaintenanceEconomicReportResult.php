<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Reporting\ReadMaintenanceEconomicReport;

use MaintenanceCost\Application\Contract\Reporting\MaintenanceEconomicReport;
use Shared\Application\Message\ResultMessage;

/**
 * Class ReadMaintenanceEconomicReportResult
 *
 * Returns reconciled full-scope totals alongside a page of rows.
 *
 * @category Result
 */
final readonly class ReadMaintenanceEconomicReportResult implements ResultMessage
{
  public function __construct(public MaintenanceEconomicReport $report)
  {
  }
}
