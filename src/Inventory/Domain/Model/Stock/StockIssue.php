<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

/** @category Model */
final readonly class StockIssue
{
  public function __construct(public StockValuation $balance, public ?string $totalValue, public ?string $unitCost)
  {
  }
}
