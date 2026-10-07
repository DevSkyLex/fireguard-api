<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/** @category DTO */
final class InventoryBalanceOutput
{
  #[Groups(['inventory:read'])]
  #[ApiProperty(identifier:true)]
  public string $id = '';

  #[Groups(['inventory:read'])]
  public string $partId = '';

  #[Groups(['inventory:read'])]
  public string $warehouseId = '';

  #[Groups(['inventory:read'])]
  public string $quantity = '';

  #[Groups(['inventory:read'])]
  public ?InventoryValuationOutput $valuation = null;
}
