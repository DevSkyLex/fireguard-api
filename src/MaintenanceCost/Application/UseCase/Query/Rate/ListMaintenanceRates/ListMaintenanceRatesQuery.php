<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Rate\ListMaintenanceRates;

use Shared\Application\Message\QueryMessage;

/** Bounded organization rates query with an optional scoped member filter. */
final readonly class ListMaintenanceRatesQuery implements QueryMessage
{
  public function __construct(public string $organizationId, public string $actorUserId, public ?string $memberId = null, public int $page = 1, public int $itemsPerPage = 30)
  {
  }
}
