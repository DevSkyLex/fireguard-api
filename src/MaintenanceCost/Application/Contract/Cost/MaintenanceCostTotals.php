<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Cost;

/** Class MaintenanceCostTotals. Separates the known subtotal from an incomplete realized cost. @category Contract */
final readonly class MaintenanceCostTotals
{
  /**
   * @param list<MaintenanceCostItem> $items
   */
  public function __construct(public ?string $total, public string $knownTotal, public bool $complete, public array $items)
  {
  }
}
