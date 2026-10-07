<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

use DateTimeImmutable;

/** @category Model */
final readonly class StockMovement
{
  public function __construct(public string $id, public string $organizationId, public string $partId, public string $warehouseId, public string $kind, public string $quantity, public ?string $unitCost, public ?string $totalValue, public string $currency, public string $reason, public string $actorId, public DateTimeImmutable $occurredAt, public ?string $interventionId = null, public ?string $workItemId = null, public ?string $equipmentId = null, public ?string $correctionOf = null, public ?string $sourceReceiptId = null, public bool $late = false)
  {
  }
}
