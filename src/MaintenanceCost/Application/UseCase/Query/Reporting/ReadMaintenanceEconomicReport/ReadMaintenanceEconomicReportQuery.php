<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Reporting\ReadMaintenanceEconomicReport;

use Shared\Application\Message\QueryMessage;

/**
 * Class ReadMaintenanceEconomicReportQuery
 *
 * Requests an exact bounded private aggregate, independent of operational read permissions.
 *
 * @category Query
 */
final readonly class ReadMaintenanceEconomicReportQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public string $from, public string $to, public string $groupBy = 'equipment', public ?string $siteId = null, public ?string $customerId = null, public ?string $equipmentId = null, public int $page = 1, public int $itemsPerPage = 30)
  {
  }
}
