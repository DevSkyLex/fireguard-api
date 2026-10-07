<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Rate\ListMaintenanceRates;

use MaintenanceCost\Application\Port\Outbound\Rate\MaintenanceRateStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostAccessGuard;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Message\QueryHandler;

use function preg_match;
use function strtolower;

/** Enforces financial permissions before disclosing any member rates or filter scope. */
final readonly class ListMaintenanceRatesHandler implements QueryHandler
{
  public function __construct(private MaintenanceRateStorePort $store, private MaintenanceCostAccessGuard $access, private OrganizationWorkforceDirectoryPort $workforce)
  {
  }

  public function __invoke(ListMaintenanceRatesQuery $query): ListMaintenanceRatesResult
  {
    $this->access->assertAccess($query->actorUserId, $query->organizationId, false);
    $memberId = null === $query->memberId ? null : strtolower($query->memberId);
    if (null !== $memberId && (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $memberId) || !isset($this->workforce->profiles($query->organizationId, [$memberId])[$memberId]))) {
      throw MaintenanceCostException::notFound();
    }
    if ($query->page < 1 || $query->itemsPerPage < 1 || $query->itemsPerPage > 100 || $query->page > 1000000) {
      throw MaintenanceCostException::invalid('Rate pagination must be a positive bounded page and 1 to 100 items.');
    }

    return new ListMaintenanceRatesResult($this->store->list($query->organizationId, $query->itemsPerPage, ($query->page - 1) * $query->itemsPerPage, $memberId), $this->store->count($query->organizationId, $memberId), $query->page, $query->itemsPerPage);
  }
}
