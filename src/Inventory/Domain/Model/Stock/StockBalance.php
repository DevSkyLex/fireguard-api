<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

/** @category Model */
final readonly class StockBalance
{
  public function __construct(public string $id, public string $organizationId, public string $partId, public string $warehouseId, public string $quantity, public ?string $totalValue, public string $currency)
  {
  }
}
