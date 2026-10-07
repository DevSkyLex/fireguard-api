<?php

declare(strict_types=1);

namespace Inventory\Application\Contract\Stock;

use DateTimeImmutable;

/** @category Contract */
final readonly class InventoryCostFact
{
  public function __construct(public string $factId, public string $partId, public string $quantity, public ?string $unitCost, public ?string $exactAmount, public string $currency, public DateTimeImmutable $movementAt, public ?string $workItemId, public ?string $correctionOf = null, public ?string $equipmentId = null)
  {
  }
}
