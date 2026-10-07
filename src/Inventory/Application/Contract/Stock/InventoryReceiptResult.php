<?php

declare(strict_types=1);

namespace Inventory\Application\Contract\Stock;

/** @category Contract */
final readonly class InventoryReceiptResult
{
  public function __construct(public string $movementId, public string $quantity, public ?string $unitCost, public ?string $totalValue, public bool $replayed = false, public ?string $blockedReason = null)
  {
  }
}
