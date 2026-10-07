<?php

declare(strict_types=1);

namespace Inventory\Application\Contract\Directory;

/** Nonfinancial current catalog identity, including archived references. @category Contract */
final readonly class InventoryPartDescriptor
{
  public function __construct(public string $id, public string $code, public string $label, public string $unit, public bool $archived)
  {
  }
}
