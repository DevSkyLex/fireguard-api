<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/** @category DTO */
final class InventoryValuationOutput
{
  #[Groups(['inventory:read'])]
  public ?string $unitCost = null;

  #[Groups(['inventory:read'])]
  public ?string $totalValue = null;

  #[Groups(['inventory:read'])]
  public string $currency = '';

  #[Groups(['inventory:read'])]
  public bool $incomplete = false;
}
