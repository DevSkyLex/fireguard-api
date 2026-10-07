<?php

declare(strict_types=1);

namespace Procurement\Application\UseCase\Query\ReadProcurement;

use Shared\Application\Message\ResultMessage;

/** Every row has already passed organization and financial visibility checks. */
final readonly class ReadProcurementResult implements ResultMessage
{
  /**
   * @param list<array<string,mixed>> $items
   */
  public function __construct(public string $kind, public array $items, public bool $collection, public int $total = 1, public int $page = 1, public int $itemsPerPage = 30)
  {
  }
}
