<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Query\ListInventory;

use Shared\Application\Message\QueryMessage;

/** @category UseCase */
final readonly class ListInventoryQuery implements QueryMessage
{
  /**
   * @param array<string,string> $filters
   */
  public function __construct(public string $organizationId, public string $type, public array $filters = [], public int $page = 1, public int $itemsPerPage = 30, public ?string $id = null)
  {
  }
}
