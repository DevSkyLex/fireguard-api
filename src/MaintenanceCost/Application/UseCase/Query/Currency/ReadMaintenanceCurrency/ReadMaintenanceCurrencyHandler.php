<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Currency\ReadMaintenanceCurrency;

use MaintenanceCost\Application\Port\Outbound\Currency\MaintenanceCurrencyStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostAccessGuard;
use Shared\Application\Message\QueryHandler;

/** Keeps financial configuration private to entitled organization members. */
final readonly class ReadMaintenanceCurrencyHandler implements QueryHandler
{
  public function __construct(private MaintenanceCurrencyStorePort $store, private MaintenanceCostAccessGuard $access)
  {
  }

  public function __invoke(ReadMaintenanceCurrencyQuery $query): ReadMaintenanceCurrencyResult
  {
    $this->access->assertAccess($query->actorUserId, $query->organizationId, false);

    return new ReadMaintenanceCurrencyResult($this->store->read($query->organizationId));
  }
}
