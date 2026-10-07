<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Rate\ListMaintenanceRates;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use Shared\Application\Message\ResultMessage;

/** Stable pagination over append-only rate parameters. */
final readonly class ListMaintenanceRatesResult implements ResultMessage
{
  /**
   * @param list<MaintenanceRateSnapshot> $items
   */
  public function __construct(public array $items, public int $total, public int $page, public int $itemsPerPage)
  {
  }
}
