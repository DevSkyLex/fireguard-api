<?php

declare(strict_types=1);

namespace Inventory\Application\Contract\Stock;

/** @category Contract */
final readonly class InventoryReceiptRequest
{
  public function __construct(public string $organizationId, public string $partId, public string $warehouseId, public string $quantity, public ?string $unitCost, public string $currency, public string $clientOperationId, public string $sourceReceiptId, public string $actorId)
  {
  }
}
