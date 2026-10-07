<?php

declare(strict_types=1);

namespace Inventory\Application\Contract\Stock;

/** @category Contract */
final readonly class InventoryReceiptReturnRequest
{
  public function __construct(public string $organizationId, public string $movementId, public string $quantity, public string $reason, public string $clientOperationId, public string $actorId)
  {
  }
}
