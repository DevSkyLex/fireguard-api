<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Query\ListInventory;

use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};

/** @category UseCase */
final readonly class ListInventoryResult implements \Shared\Application\Message\ResultMessage
{
  /**
   * @param list<InventoryReference|StockBalance|StockMovement|ConsumptionDeclaration> $items
   */
  public function __construct(public array $items, public int $totalItems)
  {
  }
}
