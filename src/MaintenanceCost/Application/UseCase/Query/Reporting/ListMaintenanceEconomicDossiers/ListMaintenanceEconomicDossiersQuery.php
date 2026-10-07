<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Reporting\ListMaintenanceEconomicDossiers;

use Shared\Application\Message\QueryMessage;

/**
 * Class ListMaintenanceEconomicDossiersQuery
 *
 * Requests a paginated minimal finance directory without operational entitlements.
 *
 * @category Query
 */
final readonly class ListMaintenanceEconomicDossiersQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public int $page = 1, public int $itemsPerPage = 30, public ?string $search = null, public ?string $from = null, public ?string $to = null, public ?string $siteId = null, public ?string $customerId = null, public ?string $equipmentId = null)
  {
  }
}
