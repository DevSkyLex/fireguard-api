<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Plan\ReadMaintenancePlans;

use DateTimeImmutable;
use Maintenance\Application\Contract\Plan\MaintenancePlanDetails;
use Shared\Application\Message\ResultMessage;

/** Typed read page and pure date preview. */
final readonly class ReadMaintenancePlansResult implements ResultMessage
{
  /**
   * @param list<MaintenancePlanDetails> $items
   * @param list<DateTimeImmutable> $dates
   */
  public function __construct(public array $items = [], public int $total = 0, public array $dates = [], public string $mode = 'legacy', public int $preparedCount = 0)
  {
  }
}
